<?php
class HbAvailableAccom {

	private $hbdb;
	private $utils;
	private $strings;
	private $price_calc;
	private $options_form;

	public function __construct( $hbdb, $utils, $strings, $price_calc, $options_form ) {
		$this->hbdb = $hbdb;
		$this->utils = $utils;
		$this->strings = $strings;
		$this->price_calc = $price_calc;
		$this->options_form = $options_form;
	}

	public function get_available_accom( $search_request ) {
		$str_check_in = $search_request['check_in'];
		$str_check_out = $search_request['check_out'];
		$adults = $search_request['adults'];
		$children = $search_request['children'];
		$page_accom_id = $search_request['page_accom_id'];
		$current_page_id = $search_request['current_page_id'];
		$is_admin = $search_request['is_admin'];
		$admin_accom_id = $search_request['admin_accom_id'];
		$admin_search_type = $search_request['admin_search_type'];
		$accom_people = array();
		if ( ( $search_request['accom_people'] ) && ( $admin_search_type != 'single_accom' ) ) {
			$adults = 0;
			$children = 0;
			$a = explode( ',', $search_request['accom_people'] );
			foreach ( $a as $p ) {
				$b = explode( '-', $p );
				$accom_people[] = array(
					'adults' => $b[0],
					'children' => $b[1]
				);
				$adults += $b[0];
				$children += $b[1];
			}
		}

		if ( $is_admin == 'yes' ) {
			$this->strings['no_accom_can_suit_one_person'] = esc_html__( 'Could not find any accommodation that would suit one person only.', 'hbook-admin' );
			$this->strings['no_accom_can_suit_nb_people'] = esc_html__( 'Could not find any accommodation that would suit %persons_nb persons.', 'hbook-admin' );
			$this->strings['no_accom_can_suit_nb_people_only'] = esc_html__( 'Could not find any accommodation that would suit %persons_nb persons only.', 'hbook-admin' );
			$this->strings['chosen_adults'] = esc_html__( 'Adults:', 'hbook-admin' );
			$this->strings['chosen_children'] = esc_html__( 'Children:', 'hbook-admin' );
			$this->strings['select_accom_button'] = esc_html__( 'Select', 'hbook-admin' );
			$this->strings['selected_accom'] = esc_html__( 'This accommodation is selected.', 'hbook-admin' );
			$this->strings['multi_accom_accom_n'] = esc_html__( 'Accommodation %n', 'hbook-admin' );
			$this->strings['select_accom_num_title'] = esc_html__( 'Select accommodation number', 'hbook-admin' );
			$this->strings['select_accom_num_select_title'] = esc_html__( 'Select accommodation number', 'hbook-admin' );
		}

		if ( $this->utils->nb_accom() == 0 ) {
			return array(
				'success' => false,
				'msg' => 'Unexpected error (no accommodation defined).'
			);
		}

		$validation = $this->utils->validate_date_and_people( $str_check_in, $str_check_out, $adults, $children );
		if ( ! $validation['success'] ) {
			return array(
				'success' => false,
				'msg' => wp_kses_post( 'Error (' . $validation['error_msg'] . ').' )
			);
		}

		$nb_nights = $this->utils->get_number_of_nights( $str_check_in, $str_check_out );
		$nb_people = $adults + $children;

		$output = '';

		if ( $admin_search_type == 'single_accom' ) {
			if ( $admin_accom_id == 'all' ) {
				$accom = $this->hbdb->get_all_accom_ids();
			} else {
				$accom = array( $admin_accom_id );
			}
			$available_accom_dates = $this->hbdb->get_available_accom_per_dates( $str_check_in, $str_check_out );
			$accom_suit_people = $this->hbdb->get_accom_per_occupancy( $adults, $children );
			$accom_suit_people_type_sensitive = $this->hbdb->get_accom_per_occupancy_type_sensitive( $adults, $children );

			$available_accom = array();
			if ( $admin_accom_id == 'all' ) {
				$output .= '<div class="hb-filter-accom-list-wrapper">';
				if ( count( $accom ) != count( $available_accom_dates ) ) {
					$output .= '<p>';
					$output .= '<input id="hb-show-unavailable-accom" class="hb-filter-accom-list" type="checkbox" />';
					$output .= '<label for="hb-show-unavailable-accom"> ';
					$output .= esc_html__( 'Show unavailable accommodation at the chosen dates', 'hbook-admin' );
					$output .= '</label>';
					$output .= '</p>';
				}
				if (
					( count( $accom ) != count( $accom_suit_people ) ) ||
					( count( $accom ) != count( $accom_suit_people_type_sensitive ) )
				) {
					$output .= '<p>';
					$output .= '<input id="hb-show-people-unsuitable-accom" class="hb-filter-accom-list" type="checkbox" />';
					$output .= '<label for="hb-show-people-unsuitable-accom"> ';
					if ( count( $accom_suit_people ) == count( $accom_suit_people_type_sensitive ) ) {
						if ( $nb_people == 1 ) {
							$output .= sprintf( esc_html__( 'Show accommodation not suitable for 1 person', 'hbook-admin' ), $nb_people );
						} else {
							$output .= sprintf( esc_html__( 'Show accommodation not suitable for %s persons', 'hbook-admin' ), $nb_people );
						}
					} else {
						$output .= sprintf( esc_html__( 'Show accommodation not suitable for %s adult(s) and %s child(ren)', 'hbook-admin' ), $adults, $children );
					}
					$output .= '</label>';
					$output .= '</p>';
				}
				$output .= '</div>';
			}
			$available_accom = array(
				array(
					'ids' => $accom,
					'adults' => $adults,
					'children' => $children
				)
			);
		}

		$is_accom_page = false;
		$accom_name = '';
		if ( $is_admin != 'yes' ) {
			if ( $page_accom_id ) {
				$is_accom_page = true;
				$accom_name = $this->utils->get_accom_title( $page_accom_id );
			}

			if ( $search_request['exists_main_booking_form'] == 'yes' ) {
				$exists_main_booking_form = true;
			} else {
				$exists_main_booking_form = false;
			}

			if ( $search_request['force_display_thumb'] == 'yes' ) {
				$force_display_thumb = true;
			} else {
				$force_display_thumb = false;
			}

			if ( $search_request['force_display_desc'] == 'yes' ) {
				$force_display_desc = true;
			} else {
				$force_display_desc = false;
			}
		}

		$search_for_multiple_accom = false;
		if (
			( ( $is_admin != 'yes' ) && ( get_option( 'hb_multiple_accom_booking_front_end' ) == 'enabled' ) ) ||
			( $admin_search_type == 'multiple_accom' )
		) {
			$search_for_multiple_accom = true;
		}

		if (
			( $search_for_multiple_accom && ( $nb_people < $this->hbdb->get_global_accom_min_occupancy() ) ) ||
			( $search_for_multiple_accom && ( $nb_people > $this->hbdb->get_multi_accom_max_occupancy() ) ) ||
			( ( $is_admin != 'yes' ) && ! $search_for_multiple_accom && ! $this->hbdb->get_accom_per_occupancy_type_sensitive( $adults, $children ) )
		) {
			if ( $nb_people == 1 ) {
				$msg = $this->strings['no_accom_can_suit_one_person'];
			} else {
				if ( $nb_people < $this->hbdb->get_global_accom_min_occupancy() ) {
					$msg = $this->strings['no_accom_can_suit_nb_people_only'];
				} else {
					$msg = $this->strings['no_accom_can_suit_nb_people'];
				}
				$msg = str_replace( '%persons_nb', $nb_people, $msg );
			}
			return array(
				'success' => false,
				'msg' => wp_kses_post( $msg )
			);
		}

		if (
			$is_accom_page &&
			! $search_for_multiple_accom &&
			(
				( $nb_people > get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) ||
				( $nb_people < get_post_meta( $page_accom_id, 'accom_min_occupancy', true ) ) ||
				(
					( ! empty( get_post_meta( $page_accom_id, 'accom_max_adults', true ) )
						&& ( get_post_meta( $page_accom_id, 'accom_max_adults', true ) < $adults )
					)
				) ||
				(
					( ! empty( get_post_meta( $page_accom_id, 'accom_max_children', true ) )
						&& ( get_post_meta( $page_accom_id, 'accom_max_children', true ) < $children )
					)
				)
			)
		) {
			$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
			$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
			if ( $max_adults && ( $adults > $max_adults ) ) {
				// Unfortunately the %1$s can not suit %2$s adults.
				if ( $adults == 1 ) {
					$msg_part1 = $this->strings['accom_can_not_suit_one_adult'];
				} else {
					$msg_part1 = $this->strings['accom_can_not_suit_nb_adults'];
					$msg_part1 = str_replace( '%adults_nb', $adults, $msg_part1 );
				}
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else if ( $max_children && ( $children > $max_children ) ) {
				// Unfortunately the %1$s can not suit %2$s children.
				if ( $adults == 1 ) {
					$msg_part1 = $this->strings['accom_can_not_suit_one_child'];
				} else {
					$msg_part1 = $this->strings['accom_can_not_suit_nb_children'];
					$msg_part1 = str_replace( '%children_nb', $children, $msg_part1 );
				}
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else {
				// Unfortunately the %1$s can not suit %2$s persons.
				if ( $nb_people == 1 ) {
					$msg_part1 = $this->strings['accom_can_not_suit_one_person'];
				} else {
					$msg_part1 = $this->strings['accom_can_not_suit_nb_people'];
					$msg_part1 = str_replace( '%persons_nb', $nb_people, $msg_part1 );
				}
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );

				// View all available accommodation for %s persons.
				if ( $nb_people == 1 ) {
					$msg_part2 = $this->strings['view_accom_for_one_person'];
				} else {
					$msg_part2 = $this->strings['view_accom_for_persons'];
					$msg_part2 = str_replace( '%persons_nb', $nb_people, $msg_part2 );
				}
			}
			
			$msg = $msg_part1;
			if ( $msg_part2 != '' && $exists_main_booking_form ) {
				$msg .= '<br/><a href="#" class="hb-other-search">' . $msg_part2 . '</a>';
			}

			return array(
				'success' => false,
				'msg' => wp_kses_post( $msg )
			);
		}

		$multi_accom_type = '';
		if ( $accom_people && $search_for_multiple_accom ) {
			$available_accom = $this->available_multi_accom_per_people_dates( $accom_people, $str_check_in, $str_check_out );
			$multi_accom_type = 'multiple';
		} else if ( $admin_search_type != 'single_accom' ) {
			$available_accom = $this->available_single_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out );
			$multi_accom_type = 'single';
		}
		if ( ! $available_accom && $search_for_multiple_accom && ( get_option( 'hb_multiple_accom_booking_suggestions' ) == 'enabled' ) ) {
			$available_accom = $this->available_suggest_multi_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out );
			if ( $available_accom ) {
				$multi_accom_type = 'suggested-from-' . $multi_accom_type;
			}
		}
		if ( $is_accom_page ) {
			$accom_page_multi_accom_type = '';
			$accom_page_available_accom = array();
			$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
			$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
			if ( $accom_people ) {
				foreach ( $accom_people as $people_numbers ) {
					$accom_nb_people = $people_numbers['adults'] + $people_numbers['children'];
					if (
						( $accom_nb_people > get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) ||
						( $accom_nb_people < get_post_meta( $page_accom_id, 'accom_min_occupancy', true ) ) ||
						( $max_adults && ( $people_numbers['adults'] > $max_adults ) ) ||
						( $max_children && ( $people_numbers['children'] > $max_children ) )
					) {
						$accom_page_available_accom = array();
						break;
					} else {
						$accom_page_available_accom[] = array(
							'ids' => array( $page_accom_id ),
							'adults' => $people_numbers['adults'],
							'children' => $people_numbers['children']
						);
					}
				}
			} else {
				if (
					( $nb_people <= get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) &&
					( $nb_people >= get_post_meta( $page_accom_id, 'accom_min_occupancy', true ) ) &&
					( empty( $max_adults ) || ( $max_adults >= $adults ) ) &&
					( empty( $max_children ) || ( $max_children >= $children ) ) &&
					in_array( $page_accom_id, $available_accom[0]['ids'] )
				) {
					$accom_page_available_accom = array(
						array(
							'ids' => array( $page_accom_id ),
							'adults' => $adults,
							'children' => $children
						),
					);
				}
			}
			if (
				! $accom_page_available_accom &&
				$search_for_multiple_accom &&
				( get_post_meta( $page_accom_id, 'excluded_from_multiple_accom_booking', true ) != 'yes' )
			) {
				$accom_page_available_accom = $this->available_suggest_multi_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out, $page_accom_id );
				if ( $accom_page_available_accom ) {
					if ( $accom_people ) {
						$accom_page_multi_accom_type = 'suggested-from-multiple';
					} else {
						$accom_page_multi_accom_type = 'suggested-from-single';
					}
				} else {
					$accom_people_problematical = array();
					if ( $accom_people ) {
						foreach ( $accom_people as $i => $people_numbers ) {
							$accom_nb_people = $people_numbers['adults'] + $people_numbers['children'];
							if ( $i == 0 ) {
								$accom_people_min_nb = $accom_nb_people;
								$accom_people_max_nb = $accom_nb_people;
							} else {
								if ( $accom_nb_people < $accom_people_min_nb ) {
									$accom_people_min_nb = $accom_nb_people;
								}
								if ( $accom_nb_people > $accom_people_max_nb ) {
									$accom_people_max_nb = $accom_nb_people;
								}
							}
						}
						if ( $accom_people_min_nb < get_post_meta( $page_accom_id, 'accom_min_occupancy', true ) ) {
							$accom_people_problematical = array(
								'type' => 'occupancy',
								'nb' => $accom_people_min_nb,
							);
						}
						if ( $accom_people_max_nb > get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) {
							$accom_people_problematical = array(
								'type' => 'occupancy',
								'nb' => $accom_people_max_nb,
							);
						}
						if ( ! $accom_people_problematical ) {
							$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
							$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
							if ( $max_adults && ( $people_numbers['adults'] > $max_adults ) ) {
								$accom_people_problematical = array(
									'type' => 'adults',
									'nb' => $people_numbers['adults'],
								);
							}
							if ( $max_children && ( $people_numbers['children'] > $max_children ) ) {
								$accom_people_problematical = array(
									'type' => 'children',
									'nb' => $people_numbers['children'],
								);
							}
						}
					} else {
						if (
							( $nb_people > get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) ||
							( $nb_people < get_post_meta( $page_accom_id, 'accom_min_occupancy', true ) )
						) {
							$accom_people_problematical = array(
								'type' => 'occupancy',
								'nb' => $nb_people,
							);
						}
						if ( ! $accom_people_problematical ) {
							$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
							$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
							if ( $max_adults && ( $adults > $max_adults ) ) {
								$accom_people_problematical = array(
									'type' => 'adults',
									'nb' => $adults,
								);
							}
							if ( $max_children && ( $children > $max_children ) ) {
								$accom_people_problematical = array(
									'type' => 'children',
									'nb' => $children,
								);
							}
						}
					}

					if ( $accom_people_problematical ) {
						if ( 'occupancy' == $accom_people_problematical['type'] ) {
							// Unfortunately the %1$s can not suit %2$s persons.
							// View all available accommodation for %s persons.
							if ( $accom_people_problematical['nb'] == 1 ) {
								$msg_part1 = $this->strings['accom_can_not_suit_one_person'];
								$msg_part2 = $this->strings['view_accom_for_one_person'];
							} else {
								$msg_part1 = $this->strings['accom_can_not_suit_nb_people'];
								$msg_part1 = str_replace( '%persons_nb', $accom_people_problematical['nb'], $msg_part1 );
								$msg_part2 = $this->strings['view_accom_for_persons'];
								$msg_part2 = str_replace( '%persons_nb', $accom_people_problematical['nb'], $msg_part2 );
							}
							$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
						} else if ( 'adults' == $accom_people_problematical['type'] ) {
							// Unfortunately the %1$s can not suit %2$s adults.
							if ( $accom_people_problematical['nb'] == 1 ) {
								$msg_part1 = $this->strings['accom_can_not_suit_one_adult'];
							} else {
								$msg_part1 = $this->strings['accom_can_not_suit_nb_adults'];
								$msg_part1 = str_replace( '%adults_nb', $accom_people_problematical['nb'], $msg_part1 );
							}
							$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
						} else {
							// Unfortunately the %1$s can not suit %2$s children.
							if ( $accom_people_problematical['nb'] == 1 ) {
								$msg_part1 = $this->strings['accom_can_not_suit_one_child'];
							} else {
								$msg_part1 = $this->strings['accom_can_not_suit_nb_children'];
								$msg_part1 = str_replace( '%children_nb', $accom_people_problematical['nb'], $msg_part1 );
							}
							$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
						}

						$msg = $msg_part1;
						$msg_part2 = $this->strings['view_accom_at_chosen_date'];
						if ( $available_accom && $msg_part2 != '' && $exists_main_booking_form ) {
							$msg .= '<br/><a href="#" class="hb-other-search">' . $msg_part2 . '</a>';
						}
						return array(
							'success' => false,
							'msg' => wp_kses_post( $msg )
						);
					}
				}
			}
			if ( $accom_page_available_accom ) {
				$available_accom = $accom_page_available_accom;
			}
		}

		if ( $search_request['results_show_only_accom_id'] ) {
			$results_show_only_accom_id = explode( ',', $search_request['results_show_only_accom_id'] );
			if (
				( count ( array_unique( $results_show_only_accom_id ) ) == 1 ) &&
				( count( $available_accom ) != count( $results_show_only_accom_id ) )
			) {
				$available_accom = $this->available_suggest_multi_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out, $results_show_only_accom_id[0] );
			}
		}

		if ( ! $available_accom ) {
			// Unfortunately we could not find any accommodation for the dates you entered.
			// Unfortunately we could not find any accommodation for the dates you entered. You might consider checking the availability page to enter search criteria that will match the rooms availability.
			$msg = $this->strings['no_accom_at_chosen_dates'];
			return array(
				'success' => false,
				'msg' => wp_kses_post( $msg )
			);
		}

		if ( $is_accom_page && $search_for_multiple_accom ) {
			$msg_part1 = '';
			if ( ( count( $accom_people ) > 1 ) && ( get_post_meta( $page_accom_id, 'excluded_from_multiple_accom_booking', true ) == 'yes' ) ) {
				// You can not book multiple %accom_name in one reservation.
				$msg_part1 = $this->strings['accom_no_multiple_accom_booking'];
				if ( ! $msg_part1 ) {
					$msg_part1 = 'You can not book multiple %accom_name in one reservation.';
				}
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else if ( count( $accom_people ) > get_post_meta( $page_accom_id, 'accom_quantity', true ) ) {
				// We only have %available_accom_nb %accom_name.
				$msg_part1 = $this->strings['only_x_accom'];
				if ( ! $msg_part1 ) {
					$msg_part1 = 'We only have %available_accom_nb %accom_name.';
				}
				$msg_part1 = str_replace( '%available_accom_nb', get_post_meta( $page_accom_id, 'accom_quantity', true ), $msg_part1 );
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else if ( $nb_people > get_post_meta( $page_accom_id, 'accom_quantity', true ) * get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) {
				// We do not have enough %accom_name to suit %persons_nb persons.
				$msg_part1 = $this->strings['not_enough_accom_for_people'];
				if ( ! $msg_part1 ) {
					$msg_part1 = 'We do not have enough %accom_name to suit %persons_nb persons.';
				}
				$msg_part1 = str_replace( '%persons_nb', $nb_people, $msg_part1 );
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else {
				$unavailable_accom = $this->hbdb->get_unavailable_accom_num_per_date( $page_accom_id, $str_check_in, $str_check_out );
				$available_accom_quantity = get_post_meta( $page_accom_id, 'accom_quantity', true ) - count( $unavailable_accom );
				$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
				$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
				if ( count( $accom_people ) > $available_accom_quantity ) {
					// There is only %available_accom_nb %accom_name available at the chosen dates.
					$msg_part1 = $this->strings['only_x_accom_available_at_chosen_dates'];
					if ( ! $msg_part1 ) {
						$msg_part1 = 'There are only %available_accom_nb %accom_name available at the chosen dates.';
					}
					$msg_part1 = str_replace( '%available_accom_nb', $available_accom_quantity, $msg_part1 );
					$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
				} else if ( $nb_people > $available_accom_quantity * get_post_meta( $page_accom_id, 'accom_max_occupancy', true ) ) {
					// We do not have enough %accom_name to suit %persons_nb persons at the chosen dates.
					$msg_part1 = $this->strings['not_enough_accom_for_people_at_chosen_dates'];
					if ( ! $msg_part1 ) {
						$msg_part1 = 'We do not have enough %accom_name to suit %persons_nb persons at the chosen dates.';
					}
					$msg_part1 = str_replace( '%persons_nb', $nb_people, $msg_part1 );
					$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
				} else if ( $max_adults && ( $adults > $available_accom_quantity * $max_adults ) ) {
					// Unfortunately the %1$s can not suit %2$s adults.
					$msg_part1 = $this->strings['accom_can_not_suit_nb_adults'];
					$msg_part1 = str_replace( '%adults_nb', $adults, $msg_part1 );
					$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
				} else if ( $max_children && ( $children > $available_accom_quantity * $max_children ) ) {
					// Unfortunately the %1$s can not suit %2$s children.
					$msg_part1 = $this->strings['accom_can_not_suit_nb_children'];
					$msg_part1 = str_replace( '%children_nb', $children, $msg_part1 );
					$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
				}
			}
			if ( $msg_part1 ) {
				$msg = $msg_part1;
				if ( $exists_main_booking_form ) {
					// View all available accommodation at the chosen dates.
					$msg_part2 = $this->strings['view_accom_at_chosen_date'];
					if ( $msg_part2 != '' ) {
						$msg .= '<br/><a href="#" class="hb-other-search">' . $msg_part2 . '</a>';
					}
				}

				return array(
					'success' => false,
					'msg' => wp_kses_post( $msg )
				);
			}
		}

		if ( $is_accom_page && ! $accom_people && ! in_array( $page_accom_id, $available_accom[0]['ids'] ) ) {
			$max_adults = get_post_meta( $page_accom_id, 'accom_max_adults', true );
			$max_children = get_post_meta( $page_accom_id, 'accom_max_children', true );
			if ( ( '' !== $max_adults ) && ( $adults > $max_adults ) ) {
				// Unfortunately the %1$s can not suit %2$s adults.
				$msg_part1 = $this->strings['accom_can_not_suit_nb_adults'];
				$msg_part1 = str_replace( '%adults_nb', $adults, $msg_part1 );
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else if ( ( '' !== $max_children ) && ( $children > $max_children ) ) {
				// Unfortunately the %1$s can not suit %2$s children.
				$msg_part1 = $this->strings['accom_can_not_suit_nb_children'];
				$msg_part1 = str_replace( '%children_nb', $children, $msg_part1 );
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			} else {
				// The %accom_name is not available at the chosen dates.
				$msg_part1 = $this->strings['accom_not_available_at_chosen_dates'];
				$msg_part1 = str_replace( '%accom_name', $accom_name, $msg_part1 );
			}

			if ( ! $exists_main_booking_form ) {
				$msg = $msg_part1;
				return array(
					'success' => false,
					'msg' => wp_kses_post( $msg )
				);
			} else {
				// View all available accommodation at the chosen dates.
				$msg_part2 = $this->strings['view_accom_at_chosen_date'];

				$msg = $msg_part1;
				if ( $msg_part2 != '' ) {
					$msg .= '<br/><a href="#" class="hb-other-search">' . $msg_part2 . '</a>';
				}
				return array(
					'success' => false,
					'msg' => wp_kses_post( $msg )
				);
			}
		}

		$output .= '<div class="hb-accom-step-wrapper hb-step-wrapper">';

		if ( $is_admin != 'yes' ) {
			if (
				( get_option( 'hb_multiple_accom_booking_front_end' ) == 'enabled' ) &&
				! $accom_people &&
				(
					( ! $is_accom_page && ( ( $adults > 1 ) || ( $children > 1 ) ) ) ||
					( $is_accom_page && count( $available_accom ) > 1 )
				)
			) {
				$output .= '<p class="hb-search-specific-accom-number"><a href="#">' . $this->strings['search_specific_accom_number_link'] . '</a></p>';
			}
			$intro_msg = '';
			$intro_title = '';
			if ( $is_accom_page ) {
				if ( $accom_page_multi_accom_type == 'suggested-from-single' ) {
					// More than one accommodation is needed to accommodate your stay.
					// Please check our suggestion below.
					$intro_msg = $this->strings['accom_suggestion_for_single_accom_search'];
				} else if ( $accom_page_multi_accom_type == 'suggested-from-multiple' ) {
					// We could not find any result matching your search criteria.
					// You can change your search or check our suggestion below.
					$intro_msg = $this->strings['accom_suggestion_for_multiple_accom_search'];
				}
			} else {
				if ( ( count( $available_accom ) == 1 ) && ( $multi_accom_type != 'suggested-from-multiple' ) ) {
					if ( count( $available_accom[0]['ids'] ) > 1 ) {
						// We have found %s of accommodation that suit your needs.
						$intro_msg = $this->strings['several_types_of_accommodation_found'];
						$intro_msg = str_replace( '%nb_types', count( $available_accom[0]['ids'] ), $intro_msg );
						// Select your accommodation
						$intro_title = $this->strings['select_accom_title'];
					} else {
						// We have found 1 type of accommodation that suit your needs.
						$intro_msg = $this->strings['one_type_of_accommodation_found'];
					}
				} else {
					if ( $multi_accom_type == 'multiple' ) {
						// We have found the following accommodation.
						$intro_msg = $this->strings['multi_accom_intro'];
					} else if ( $multi_accom_type == 'suggested-from-single' ) {
						// More than one accommodation is needed to accommodate your stay.
						// Please check our suggestion below.
						$intro_msg = $this->strings['accom_suggestion_for_single_accom_search'];
					} else if ( $multi_accom_type == 'suggested-from-multiple' ) {
						// We could not find any result matching your search criteria.
						// You can change your search or check our suggestion below.
						$intro_msg = $this->strings['accom_suggestion_for_multiple_accom_search'];
					}
				}
			}
			if ( $intro_msg || $intro_title ) {
				$output .= '<div class="hb-search-result-title-section">';
				if ( $intro_msg ) {
					$output .= '<p>' . $intro_msg . '</p>';
				}
				if ( $intro_title ) {
					$output .= '<h3 class="hb-title hb-title-select">' . $intro_title . '</h3>';
				}
				$output .= '</div><!-- end .hb-search-result-title-section -->';
			}
		} else if ( ( $is_admin == 'yes' ) && ( $multi_accom_type == 'suggested-from-multiple' ) ) {
			$output .= '<p>';
			$output .= esc_html__( 'Could not find any result matching the search criteria. Check the suggestion below.', 'hbook-admin' );
			$output .= '</p>';
		}

		$output .= '<input type="hidden" class="hb-booking-nb-adults" value="' . implode( '-', array_column( $available_accom, 'adults' ) ) . '" />';
		$output .= '<input type="hidden" class="hb-booking-nb-children" value="' . implode( '-', array_column( $available_accom, 'children' ) ) . '" />';

		$accom_quantity_left = $this->hbdb->get_accom_quantity_left( $str_check_in, $str_check_out, $page_accom_id );
		foreach ( $accom_quantity_left as $accom_id => $quantity ) {
			$output .= '<div class="hb-accom-quantity" data-accom-id="' . $accom_id . '" data-quantity="' . $quantity . '"></div>';
		}

		$multi_accom_choices_class = 'hb-multi-accom-choices';
		if ( $is_accom_page && ( count( $available_accom ) == 1 ) ) {
			$multi_accom_choices_class .= ' hb-accom-page-one-result';
		}
		foreach ( $available_accom as $accom_no => $accoms ) {
			$output .= '<div class="' . $multi_accom_choices_class . '">';

			if ( count( $available_accom ) > 1 || ( $multi_accom_type == 'suggested-from-multiple' ) ) {
				if ( $is_admin == 'yes' ) {
					$output .= '<p class="hb-admin-add-resa-section-title">';
					$output .= esc_html( str_replace( '%n', $accom_no + 1, esc_html__( 'Accommodation %n', 'hbook-admin' ) ) );
					$output .= '</p>';
				} else {
					if ( count( $accoms['ids'] ) > 1 ) {
						$multi_accom_title = $this->strings['multi_accom_select_accom_n'];
					} else {
						$multi_accom_title = $this->strings['multi_accom_accom_n'];
					}
					$output .= '<h4 class="hb-multi-accom-search-results-title">' . str_replace( '%n', $accom_no + 1, $multi_accom_title ) . '</h4>';
					$output .= '<p class="hb-multi-accom-no-accom-selected">' . $this->strings['multi_accom_no_accom_selected'] . '</p>';
				}
				if ( get_option( 'hb_display_adults_field' ) == 'yes' ) {
					$output .= '<p class="hb-multi-accom-search-results-people">' . $this->strings['chosen_adults'] . ' '. $accoms['adults'];
					if ( get_option( 'hb_display_children_field' ) == 'yes' ) {
						$output .= '<br/>' . $this->strings['chosen_children'] . ' '. $accoms['children'];
					}
					$output .= '</p>';
				}
			}

			$target = get_option( 'hb_accom_links_target', '_self' );
			$price_breakdown = '';
			foreach ( $accoms['ids'] as $accom_id ) {
				$prices = $this->price_calc->get_price( $accom_id, $str_check_in, $str_check_out, $accoms['adults'], $accoms['children'], $price_breakdown );
				if ( ! $prices['success'] ) {
					return array(
						'success' => false,
						'msg' => wp_kses_post( $prices['error'] )
					);
				} else {
					$price = $prices['prices']['accom_total'];
				}

				$thumb_mark_up = '';
				if (
					( $is_admin != 'yes' ) &&
					(
						( $force_display_thumb ) ||
						( ! $is_accom_page && ( get_option( 'hb_thumb_display' ) != 'no' ) )
					)
				) {
					$thumb_width = intval( get_option( 'hb_search_accom_thumb_width', 100 ) );
					if ( ! $thumb_width ) {
						$thumb_width = 100;
					}
					$thumb_height = intval( get_option( 'hb_search_accom_thumb_height', 100 ) );
					if ( ! $thumb_height ) {
						$thumb_height = 100;
					}
					$thumb_mark_up = $this->utils->get_thumb_mark_up( $accom_id, $thumb_width, $thumb_height, 'hb-accom-img' );
					if (
						$thumb_mark_up &&
						( get_option( 'hb_thumb_accom_link' ) == 'yes' ) &&
						( $current_page_id != $page_accom_id )
					) {
						$thumb_mark_up = '<a target="' . $target . '" href="' . $this->utils->get_accom_link( $accom_id ) . '">' . $thumb_mark_up . '</a>';
					}
				}

				$accom_div_class = 'hb-accom hb-accom-id-' . $accom_id;
				$not_available_tag = false;
				$not_suits_people_tag = false;
				$not_suits_sensitive_people_tag = false;

				if ( $admin_search_type == 'single_accom' ) {
					if ( $admin_accom_id == 'all' ) {
						$accom_div_class .= ' hb-accom-admin-search-type-single-accom-all';
					}
					if ( in_array( $accom_id, $available_accom_dates ) ) {
						$accom_div_class .= ' hb-accom-available';
					} else {
						$not_available_tag = true;
					}
					if ( in_array( $accom_id, $accom_suit_people_type_sensitive ) ) {
						$accom_div_class .= ' hb-accom-suits-people';
					} else {
						if ( in_array( $accom_id, $accom_suit_people ) ) {
							$not_suits_sensitive_people_tag = true;
						} else {
							$not_suits_people_tag = true;
						}
					}
				}

				$output .= '<div class="' . $accom_div_class . '" data-accom-id="' . $accom_id . '">';

				if ( $not_available_tag ) {
					$output .= '<p><small>';
					$output .= esc_html__( 'Not available at the chosen dates.', 'hbook-admin' );
					$output .= '</small></p>';
				}
				if ( $not_suits_people_tag ) {
					$output .= '<p><small>';
					if ( $nb_people == 1 ) {
						$output .= esc_html__( 'Not suitable for 1 person.', 'hbook-admin' );
					} else {
						$output .= esc_html( sprintf( esc_html__( 'Not suitable for %s persons.', 'hbook-admin' ), $nb_people ) );
					}
					$output .= '</small></p>';
				}
				if ( $not_suits_sensitive_people_tag ) {
					$output .= '<p><small>';
					$output .= esc_html( sprintf( esc_html__( 'Not suitable for %s adult(s) and %s child(ren).', 'hbook-admin' ), $adults, $children ) );
					$output .= '</small></p>';
				}

				$output .= $thumb_mark_up;

				if ( $is_admin == 'yes' ) {
					$output .= '<p>';
					$output .= '<span class="hb-accom-title">' . $this->utils->get_admin_accom_title( $accom_id ) . '</span>';
					$output .= '<span class="hb-accom-price"> - ' . $this->utils->price_with_symbol( $price ) . '</span>';
					$output .= '</p>';
					$output .= '<p class="hb-price-breakdown">' . $price_breakdown . '</p>';
				} else {
					if ( $is_accom_page ) {
						if ( count( $available_accom ) == 1 ) {
							// The %accom_name is available at the chosen dates.
							$msg = $this->strings['accom_available_at_chosen_dates'];
							$msg = str_replace( '%accom_name', $accom_name, $msg );
							$output .= '<div class="hb-accom-desc">' . $msg;
							if ( $force_display_desc ) {
								if ( $msg ) {
									$output .= '<br/>';
								}
								$output .= $this->utils->get_accom_search_desc( $accom_id );
							}
							$output .= '</div>';
						} else {
							$output .= '<div class="hb-accom-title">' . $accom_name . '</div>';
						}
					} else {
						$title = $this->utils->get_accom_title( $accom_id );
						if ( get_option( 'hb_title_accom_link' ) == 'yes' ) {
							$title = '<a target="' . $target . '" href="' . $this->utils->get_accom_link( $accom_id ) . '">' . $title . '</a>';
						}
						$output .= '<div class="hb-accom-title">' . $title . '</div>';
						$output .= '<div class="hb-accom-desc">' . $this->utils->get_accom_search_desc( $accom_id ) . '</div>';
					}

					// price for 1 night
					// price for %x nights
					if ( get_option( 'hb_charge_per_day' ) == 'yes' ) {
						$msg = $this->strings['price_for_several_nights'];
						$msg = str_replace( '%nb_nights', $nb_nights + 1, $msg );
					} else {
						if ( $nb_nights > 1 ) {
							$msg = $this->strings['price_for_several_nights'];
							$msg = str_replace( '%nb_nights', $nb_nights, $msg );
						} else {
							$msg = $this->strings['price_for_1_night'];
						}
					}

					if ( get_option( 'hb_display_price' ) != 'no' ) {
						$output .= '
							<div class="hb-accom-price-total hb-clearfix">
								<div class="hb-accom-price">' . $this->utils->price_with_symbol( $price ) . '</div>
								<div class="hb-accom-price-caption">' . $msg;
						if ( get_option( 'hb_display_price_breakdown' ) == 'yes' ) {
							// View price breakdown
							$msg1 = $this->strings['view_price_breakdown'];
							// Hide price breakdown
							$msg2 = $this->strings['hide_price_breakdown'];
							$output .= '
									<br/>
									<span class="hb-accom-price-caption-dash">&nbsp;-&nbsp;</span>
									<a class="hb-view-price-breakdown" href="#">
										<span class="hb-price-bd-show-text">' . $msg1 . '</span>
										<span class="hb-price-bd-hide-text">' . $msg2 . '</span>
									</a>
								</div>
							</div>
							<p class="hb-price-breakdown">' . $price_breakdown . '</p>';
						} else {
							$output .= '
									<p class="hb-hidden-price-breakdown">' . $price_breakdown . '</p>
								</div>
							</div>';
						}
					} else {
						$output .= '
							<br/>';
					}
				}

				$output .= '<div class="hb-select-accom-wrapper hb-clearfix">';
				if ( count( $accoms['ids'] ) > 1 ) {
					$output .= '<p class="hb-select-accom hb-button-wrapper">';
					// Select this accommodation
					$output .= '<input type="submit" value="' . $this->strings['select_accom_button'] . '"';
					if ( $is_admin == 'yes' ) {
						$output .= ' class="button"';
					}
					$output .= ' /></p>';
				}
				if ( ( $is_admin != 'yes' ) && ( get_option( 'hb_button_accom_link' ) == 'yes' ) ) {
					$output .= '<p class="hb-view-accom hb-button-wrapper">';
					$output .= '<input type="submit" data-accom-url="';
					$output .= $this->utils->get_accom_link( $accom_id );
					$output .= '" data-link-target="';
					$output .= $target . '" value="' . $this->strings['view_accom_button'] . '" />';
					$output .= '</p>';
				}
				$output .= '</div><!-- .hb-select-accom-wrapper -->';

				$output .= '<p class="hb-accom-selected-left-wrapper">';
				if ( $is_admin == 'yes' ) {
					$msg = esc_html__( 'This accommodation is selected.', 'hbook-admin' );
					$msg = str_replace( '%accom_name', $this->utils->get_admin_accom_title( $accom_id ), $msg );
				} else {
					$msg = $this->strings['selected_accom'];
					$msg = str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $msg );
				}
				$output .= '<span class="hb-accom-selected-name">' . $msg . '</span>';
				if ( $is_admin == 'yes' ) {
					$msg = esc_html__( 'You have already selected %selected_accom_nb %accom_name. There are no more %accom_name available.' );
				} else {
					$msg = $this->strings['nb_accom_selected'];
				}
				$msg = str_replace( '%selected_accom_nb', '<span class="hb-nb-accom-selected-nb">x</span>', $msg );
				$msg = str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $msg );
				$output .= '<span class="hb-nb-accom-selected">' . $msg . '</span>';
				if ( $is_admin != 'yes' ) {
					$output .= '<span class="hb-accom-left hb-no-accom-left">';
					$msg = $this->strings['no_accom_left'];
					$output .= str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $msg );
					$output .= '</span>';
					$output .= '<span class="hb-accom-left hb-one-accom-left">';
					$msg = $this->strings['one_accom_left'];
					$output .= str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $msg );
					$output .= '</span>';
					$output .= '<span class="hb-accom-left hb-multiple-accom-left">';
					$msg = $this->strings['accom_left'];
					$msg = str_replace( '%available_accom_nb', '<span class="hb-accom-left-nb">x</span>', $msg );
					$msg = str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $msg );
					$output .= $msg;
					$output .= '</span>';
				}
				$output .= '</p><!-- end .hb-accom-selected-left-wrapper -->';
				$output .= '</div><!-- end .hb-accom -->';
			}
			$output .= '</div><!-- end .hb-multi-accom-choices -->';
		}

		$output .= $this->utils->get_step_buttons( 'next', 1, $is_admin );

		$output .= '</div><!-- end .hb-accom-step-wrapper -->';

		$output .= '<div class="hb-intermediate-step-wrapper hb-step-wrapper">';
		$output .= $this->utils->get_step_buttons( 'previous', 1, $is_admin );
		if ( ( $is_admin == 'yes' ) || ( get_option( 'hb_select_accom_num' ) == 'yes' ) ) {
			$output .= $this->get_available_accom_num( array_column( $available_accom, 'ids' ), $str_check_in, $str_check_out, $is_admin );
		}
		if ( $is_admin == 'yes' ) {
			$output .= $this->options_form->get_options_form_markup_backend( $available_accom, $nb_nights );
		} else {
			$output .= $this->options_form->get_options_form_markup_frontend( $available_accom, $nb_nights );
		}
		$output .= $this->utils->get_step_buttons( 'next', 2, $is_admin );
		$output .= '</div><!-- end .hb-intermediate-step-wrapper -->';

		$output = apply_filters( 'hb_available_accommodation_markup', $output );

		return array(
			'success' => true,
			'mark_up' => wp_kses( $output, $this->utils->hb_allowed_html_tags() ),
		);
	}

	private function accom_observes_opening_and_rules( $accom_id, $str_check_in, $str_check_out ) {
		if (
			( ! empty( get_post_meta( $accom_id, 'min_date_fixed', true ) ) && ( strtotime( $str_check_in ) < strtotime( get_post_meta( $accom_id, 'min_date_fixed', true ) ) ) )
			||
			( ! empty( get_post_meta( $accom_id, 'max_date_fixed', true ) ) && ( strtotime( $str_check_out ) > strtotime( get_post_meta( $accom_id, 'max_date_fixed', true ) ) ) )
		) {
			return false;
		}

		if ( ! empty( get_post_meta( $accom_id, 'min_date_days', true ) ) ) {
			$nb_days = get_post_meta( $accom_id, 'min_date_days', true );
			$tmp_date = new DateTime();
			$tmp_date->modify( "+{$nb_days} day" );
			$min_date = $tmp_date->format( 'Y-m-d' );
			if ( strtotime( $str_check_in ) < strtotime( $min_date ) ) {
				return false;
			}
		}
		if ( ! empty( get_post_meta( $accom_id, 'max_date_months', true ) ) ) {
			$nb_months = intval( get_post_meta( $accom_id, 'max_date_months', true ) );
			$tmp_date = new DateTime();
			$tmp_date->modify( "+{$nb_months} month" );
			$max_date = $tmp_date->format( 'Y-m-d' );
			if ( strtotime( $str_check_out ) > strtotime( $max_date ) ) {
				return false;
			}
		}
		$nb_nights = $this->utils->get_number_of_nights( $str_check_in, $str_check_out );
		$rules = $this->hbdb->get_accom_booking_rules( $accom_id );
		if ( $rules ) {
			$check_in_day = $this->utils->get_day_num( $str_check_in );
			$check_out_day = $this->utils->get_day_num( $str_check_out );
			$check_in_season = $this->hbdb->get_season( $str_check_in );
			$check_out_season = $this->hbdb->get_season( $str_check_out );
			foreach ( $rules as $rule ) {
				$allowed_check_in_days = explode( ',', $rule['check_in_days'] );
				$allowed_check_out_days = explode( ',', $rule['check_out_days'] );
				$rule_seasons = explode( ',', $rule['seasons'] );
				if (
					$rule['type'] == 'check_in_days' &&
					! in_array( $check_in_day, $allowed_check_in_days ) &&
					( $rule['all_seasons'] || in_array( $check_in_season, $rule_seasons ) )
				) {
					return false;
				} else if (
					$rule['type'] == 'check_out_days' &&
					! in_array( $check_out_day, $allowed_check_out_days ) &&
					( $rule['all_seasons'] || in_array( $check_out_season, $rule_seasons ) )
				) {
					return false;
				} else if (
					$rule['conditional_type'] != 'discount' &&
					$rule['conditional_type'] != 'special_rate' &&
					$rule['conditional_type'] != 'coupon' &&
					in_array( $check_in_day, $allowed_check_in_days ) &&
					( $rule['all_seasons'] || in_array( $check_in_season, $rule_seasons ) )
				) {
					if (
						! in_array( $check_out_day, $allowed_check_out_days ) &&
						( $rule['type'] == 'conditional' && ( $rule['conditional_type'] == 'compulsory' || $rule['conditional_type'] == 'comp_and_rate' ) )
					) {
						return false;
					} else if ( $nb_nights < $rule['minimum_stay'] ) {
						return false;
					} else if ( $nb_nights > $rule['maximum_stay'] ) {
						return false;
					}
				}
			}
		}
		return true;
	}

	private function get_available_accom_num( $accom, $check_in, $check_out, $is_admin ) {
		$output = '<form class="hb-select-accom-num-form">';
		if ( $is_admin == 'yes' ) {
			$output .= '<p class="hb-admin-add-resa-section-title">';
			$output .= esc_html__( 'Accommodation number:', 'hbook-admin' );
			$output .= '</p>';
			$option_text_format = '%accom_name (%accom_num)';
		} else {
			$output .= '<h3 class="hb-title hb-title-select-accom-num">';
			$output .= $this->strings['select_accom_num_title'];
			$output .= '</h3>';
			$accom_num_text = $this->strings['select_accom_num_text'];
			if ( $accom_num_text ) {
				$output .= '<p>';
				$output .= $accom_num_text;
				$output .= '</p>';
			}
			$option_text_format = $this->strings['select_accom_num_label'];
		}

		$avai_accom_num = array();
		$accom_num_name = array();
		foreach ( $accom as $accom_ids ) {
			foreach ( $accom_ids as $accom_id ) {
				if ( ! isset( $avai_accom_num[ $accom_id ] ) ) {
					$accom_num_name[ $accom_id ] = $this->hbdb->get_accom_num_name( $accom_id );
					$accom_num = array_keys( $accom_num_name[ $accom_id ] );
					$unavai_accom_num = $this->hbdb->get_unavailable_accom_num_per_date( $accom_id, $check_in, $check_out );
					$avai_accom_num[ $accom_id ] = array_values( array_diff( $accom_num, $unavai_accom_num ) );
				}
			}
		}

		foreach ( $accom as $accom_no => $accom_ids ) {
			$output .= '<div class="hb-select-multi-accom-num-accom-' . ( $accom_no + 1 ) . '">';
			/*
			if ( isset( $_POST['chosen_accom_num'] ) && $_POST['chosen_accom_num'] ) {
				$chosen_accom_num = $_POST['chosen_accom_num'];
			} else {
				$chosen_accom_num = $avai_accom_num[0];
			}
			*/
			foreach ( $accom_ids as $accom_id ) {
				$select_wrapper_class = 'hb-select-accom-num-accom-' . $accom_id;
				$output .= '<div class="hb-select-accom-num ' . $select_wrapper_class . '">';
				$select_id_name = $select_wrapper_class . '-multi-accom-' . ( $accom_no + 1 );
				if ( count( $accom ) > 1 ) {
					$output .= '<h4>' . str_replace( '%n', $accom_no + 1, $this->strings['multi_accom_accom_n'] ) . '</h4>';
				}
				$output .= '<select ';
				$output .= 'id="' . $select_id_name . '" ';
				$output .= 'name="' . $select_id_name . '" ';
				$output .= 'data-accom-id="' . $accom_id . '">';
				$output .= '<option value="0">';
				if ( $is_admin == 'yes' ) {
					$output .= str_replace( '%accom_name', $this->utils->get_admin_accom_title( $accom_id ), $this->strings['select_accom_num_select_title'] );
				} else {
					$output .= str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $this->strings['select_accom_num_select_title'] );
				}
				$output .= '</option>';
				foreach ( $avai_accom_num[ $accom_id ] as $i => $num ) {
					$output .= '<option value="' . $num . '"';
					/*
					if ( $num == $chosen_accom_num ) {
						$output .= 'checked ';
					}
					*/
					$output .= '>';
					if ( $is_admin == 'yes' ) {
						$option_text = str_replace( '%accom_name', $this->utils->get_admin_accom_title( $accom_id ), $option_text_format );
					} else {
						$option_text = str_replace( '%accom_name', $this->utils->get_accom_title( $accom_id ), $option_text_format );
					}
					$option_text = str_replace( '%accom_num', $accom_num_name[ $accom_id ][ $num ], $option_text );
					$output .= $option_text;
					$output .= '</option>';
				}
				$output .= '</select></div>';
			}
			$output .= '</div>';
		}
		$output .= '</form>';
		return $output;
	}

	private function available_multi_accom_per_people_dates( $accom_people, $str_check_in, $str_check_out ) {
		$accom = $this->hbdb->get_available_accom_occupancy_quantity_per_dates( $str_check_in, $str_check_out );
		$max_occupancy = array();
		$accom_quantity = array();
		foreach ( $accom as $i => $a ) {
			if (
				( $_POST['is_admin'] != 'yes' ) &&
				(
					( get_post_meta( $a['id'], 'excluded_from_multiple_accom_booking', true ) == 'yes' ) ||
					( get_post_meta( $a['id'], 'excluded_from_multiple_accom_booking', true ) == 'global-only' ) ||
					! $this->accom_observes_opening_and_rules( $a['id'], $str_check_in, $str_check_out )
				)
			) {
				unset( $accom[ $i ] );
			} else {
				$max_occupancy[] = $a['max_occupancy'];
				$accom_quantity[ $a['id'] ] = $a['quantity'];
			}
		}
		array_multisort( $max_occupancy, SORT_DESC, $accom );
		$accoms_by_max_occupancy = array();
		foreach ( $accom as $a ) {
			for ( $i = 0; $i < $a['quantity']; $i++ ) {
				$accoms_by_max_occupancy[] = $a['max_occupancy'];
			}
		}
		if ( count( $accom_people ) > count( $accoms_by_max_occupancy ) ) {
			return false;
		}
		$sorted_people_number = array();
		foreach ( $accom_people as $people_numbers ) {
			$sorted_people_number[] = $people_numbers['adults'] + $people_numbers['children'];
		}
		rsort( $sorted_people_number );
		foreach ( $sorted_people_number as $i => $p ) {
			if ( $p > $accoms_by_max_occupancy[ $i ] ) {
				return false;
			}
		}
		$returned_accoms = array();
		foreach ( $accom_people as $people_numbers ) {
			$accom = $this->hbdb->get_available_accom_per_people_dates( $people_numbers['adults'], $people_numbers['children'], $str_check_in, $str_check_out );
			foreach ( $accom as $i => $accom_id ) {
				if (
					( $_POST['is_admin'] != 'yes' ) &&
					(
						( get_post_meta( $accom_id, 'excluded_from_multiple_accom_booking', true ) == 'yes' ) ||
						( get_post_meta( $accom_id, 'excluded_from_multiple_accom_booking', true ) == 'global-only' ) ||
						! $this->accom_observes_opening_and_rules( $accom_id, $str_check_in, $str_check_out )
					)
				) {
					unset( $accom[ $i ] );
				}
			}
			if ( $accom ) {
				$returned_accoms[] = array(
					'ids' => $accom,
					'adults' => $people_numbers['adults'],
					'children' => $people_numbers['children']
				);
			} else {
				return false;
			}
		}
		do {
			$to_remove = array();
			$has_removed = false;
			foreach ( $returned_accoms as $i => $a ) {
				if ( count( $a['ids'] ) == 1 ) {
					$first_index = array_key_first( $a['ids'] );
					if ( isset( $to_remove[ $a['ids'][ $first_index ] ] ) ) {
						$to_remove[ $a['ids'][ $first_index ] ]['nb']++;
						$to_remove[ $a['ids'][ $first_index ] ]['pos'][] = $i;
					} else {
						$to_remove[ $a['ids'][ $first_index ] ] = array(
							'nb' => 1,
							'pos' => array( $i )
						);
					}
				}
			}
			foreach ( $to_remove as $id => $r ) {
				$to_be_removed = 0;
				if ( $r['nb'] > $accom_quantity[ $id ] ) {
					$to_be_removed = $r['nb'] - $accom_quantity[ $id ];
					foreach ( $returned_accoms as $i => $a ) {
						if ( ( $to_be_removed > 0 ) && ( ! in_array( $i, $r['pos'] ) ) && in_array( $id, $a['ids'] ) ) {
							$returned_accoms[ $i ]['ids'] = array_diff( $a['ids'], array( $id ) );
							$to_be_removed--;
						}
					}
					if ( $to_be_removed > 0 ) {
						return false;
					} else {
						$has_removed = true;
					}
				}
			}
		} while ( $has_removed );
		return $returned_accoms;
	}

	private function available_single_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out ) {
		$accom = $this->hbdb->get_available_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out );
		foreach ( $accom as $i => $accom_id ) {
			if ( ! $this->accom_observes_opening_and_rules( $accom_id, $str_check_in, $str_check_out ) ) {
				unset( $accom[ $i ] );
			}
		}
		if ( $accom ) {
			return array(
				array(
					'ids' => $accom,
					'adults' => $adults,
					'children' => $children
				)
			);
		} else {
			return false;
		}
	}

	private function available_suggest_multi_accom_per_people_dates( $adults, $children, $str_check_in, $str_check_out, $accom_id = false ) {
		$accom = $this->hbdb->get_available_accom_occupancy_quantity_per_dates( $str_check_in, $str_check_out );
		foreach ( $accom as $i => $a ) {
			if (
				( get_post_meta( $a['id'], 'excluded_from_multiple_accom_booking', true ) == 'yes' ) ||
				( ! $accom_id && ( get_post_meta( $a['id'], 'excluded_from_multiple_accom_booking', true ) == 'global-only' ) ) ||
				! $this->accom_observes_opening_and_rules( $a['id'], $str_check_in, $str_check_out ) ||
				( $accom_id && ( $a['id'] != $accom_id ) )
			) {
				unset( $accom[ $i ] );
			}
		}
		if ( ! $accom ) {
			return false;
		}
		$accom_distributed = false;
		if ( get_option( 'hb_multiple_accom_booking_suggest_occupancy' ) == 'normal' ) {
			$accom_distributed = $this->distribute_accom( $accom, $adults, $children, 'occupancy' );
			if ( $accom_distributed ) {
				$accom_distributed = $this->distribute_children( $accom_distributed, $adults, $children );
			}
		}
		if ( ! $accom_distributed ) {
			$accom_distributed = $this->distribute_accom( $accom, $adults, $children, 'max_occupancy' );
			if ( $accom_distributed ) {
				$accom_distributed = $this->distribute_children( $accom_distributed, $adults, $children );
			}
		}
		return $accom_distributed;
	}

	private function distribute_accom( $accom, $adults, $children, $occupancy_type ) {
		usort( $accom, function( $a, $b ) use ( $occupancy_type ) {
			if ( isset( $a['max_adults'] ) && ( $a[ 'max_adults' ] < $a[ $occupancy_type ] ) ) {
				$benchmark = $a['max_adults'];
			} else {
				$benchmark = $a[ $occupancy_type ];
			}
			if ( $benchmark == $b[ $occupancy_type ] ) {
				if ( $occupancy_type == 'max_occupancy' ) {
					$occupancy_type = 'occupancy';
				} else {
					$occupancy_type = 'max_occupancy';
				}
				if ( $benchmark == $b[ $occupancy_type ] ) {
					return 0;
				}
			}
			return ( $benchmark > $b[ $occupancy_type ] ) ? -1 : 1;
		});
		$max_occupancy = array();
		foreach ( $accom as $a ) {
			$max_occupancy[ $a['id'] ] = $a['max_occupancy'];
		}
		$children_allocated = 0;
		$adults_allocated = 0;
		$insensitive_allocated = 0;
		$returned_accoms = array();
		$people_left = $adults + $children;
		$max_adults_only = $adults;
		$max_children_only = $children;
		$distribution_margin = 0;
		$k = 0;
		while ( ( $people_left > 0 ) && ( count( $accom ) > 0 ) ) {
			if ( 50 == $k ) {
				return false;
			}
			$k++;
			if ( in_array( $people_left, array_column( $accom, $occupancy_type ) ) ) {
				foreach ( $accom as $i => $a ) {
					if ( ( $a[ $occupancy_type ] == $people_left ) &&
						(
							( ( ! isset( $a['max_adults'] ) ) && ( ! isset( $a['max_children'] ) ) ) ||
							( ( isset( $a['max_adults'] ) ) && ( $a['max_adults'] >= $max_adults_only ) ) ||
							( ( isset( $a['max_children'] ) ) && ( $a['max_children'] >= $max_children_only ) )
						)
					)
					{
						$returned_accoms[] = array(
							'id' => $a['id'],
							'people' => $people_left
						);
						$people_left = 0;
					}
				}
			}
			if ( $people_left > 0 ) {
				if ( $people_left < $accom[0]['min_occupancy'] ) {
					array_shift( $accom );
				} else {
					$sensitive_allocation = false;
					$type_order = array( 'adults', 'children' );
					if ( isset( $accom[0]['max_adults'] ) && ( $accom[0]['max_adults'] < $accom[0][ $occupancy_type ] ) ) {
						$sensitive_allocation = true;
						$max_adults_in_accom = $accom[0]['max_adults'];
					}
					else if ( isset( $accom[0]['max_children'] ) && ( $accom[0]['max_children'] < $accom[0][ $occupancy_type ] ) ) {
						$sensitive_allocation = true;
						$max_children_in_accom = $accom[0]['max_children'];
						$type_order = array_reverse( $type_order );
					}
					if ( $sensitive_allocation ) {
						$children_in_accom = 0;
						$adults_in_accom = 0;
						$max_type_in_accom = 'max_' . $type_order[0] . '_in_accom';
						$max_type_1_only = 'max_' . $type_order[0] . '_only';
						$max_type_2_only = 'max_' . $type_order[1] . '_only';
						$type_1_allocated = $type_order[0] . '_allocated';
						$type_2_allocated = $type_order[1] . '_allocated';
						$type_1_in_accom = $type_order[0] . '_in_accom';
						$type_2_in_accom = $type_order[1] . '_in_accom';

						$occupancy_remaining = $accom[0][ $occupancy_type ];
						if ( isset( $$max_type_in_accom ) ) {
							if ( ( $$max_type_in_accom >= 0 ) && ( $$max_type_1_only > 0 ) ) {
								if ( $$max_type_1_only > $$max_type_in_accom ) {
									$max_type = $$max_type_in_accom;
								} else {
									$max_type = $$max_type_1_only;
								}
								$$type_1_allocated += $max_type;
								$$type_1_in_accom = $max_type;
								$occupancy_remaining -= $max_type;
								$people_left -= $max_type;
								$$max_type_1_only -= $max_type;
							}
							if ( ( $occupancy_remaining > 0 ) && ( $people_left > 0 ) ) {
								if ( $$max_type_2_only > 0 ) {
									if ( $$max_type_2_only <= $occupancy_remaining ) {
										$max_type = $$max_type_2_only;
									} else {
										$max_type = $occupancy_remaining;
									}
									$$type_2_allocated += $max_type;
									$$type_2_in_accom = $max_type;
									$people_left -= $max_type;
									$$max_type_2_only -= $max_type;
									$occupancy_remaining -= $max_type;
								}
								if ( $occupancy_remaining > 0 ) {
									$distribution_margin += $occupancy_remaining;
								}
							}
							$people_allocated = $$type_1_in_accom + $$type_2_in_accom + $occupancy_remaining;
							if ( $people_allocated > 0 ) {
								$returned_accom = array(
									'id' => $accom[0]['id'],
									'people' => $people_allocated,
									$type_order[0] . '_max_allocated' => $$type_1_in_accom,
								);
							}
						}
					} else {
						$returned_accom = array(
							'id' => $accom[0]['id']
						);
						if ( $people_left > $accom[0][ $occupancy_type ] ) {
							$returned_accom['people'] = $accom[0][ $occupancy_type ];
							$insensitive_allocated += $accom[0][ $occupancy_type ];
							$people_left -= $accom[0][ $occupancy_type ];
						} else {
							$returned_accom['people'] = $people_left;
							$insensitive_allocated += $people_left;
							$people_left = 0;
						}
					}
					if ( $returned_accom ) {
						$returned_accoms[] = $returned_accom;
						if ( $accom[0]['quantity'] > 1 ) {
							$accom[0]['quantity']--;
						} else {
							array_shift( $accom );
						}
					}
				}
			}
		}
		if ( $people_left <= 0 ) {
			if ( $occupancy_type == 'occupancy' ) {
				if (
					( get_option( 'hb_multiple_accom_booking_avoid_singleton' ) == 'yes' ) &&
					( count( $returned_accoms ) > 1 ) &&
					( $returned_accoms[ count( $returned_accoms ) - 1 ]['people'] == 1 )
				) {
					for ( $i = count( $returned_accoms ) - 2; $i >= 0; $i-- ) {
						if (
							( ( $max_occupancy[ $returned_accoms[ $i ]['id'] ] - $returned_accoms[ $i ]['people'] ) >= 1 )
							&&
							(
								( ( ! isset( $returned_accoms[ $i ]['children_max_allocated'] ) ) && ( ! isset( $returned_accoms[ $i ]['adults_max_allocated'] ) ) ) ||
								( ( isset( $returned_accoms[ $i ]['children_max_allocated'] ) ) && ( ( isset( $returned_accoms[ $i ]['children'] ) ) && ( $returned_accoms[ $i ]['children_max_allocated'] > $returned_accoms[ $i ]['children'] ) ) ) ||
								( ( isset( $returned_accoms[ $i ]['adults_max_allocated'] ) ) && ( ( isset( $returned_accoms[ $i ]['adults'] ) ) && ( $returned_accoms[ $i ]['adults_max_allocated'] > $returned_accoms[ $i ]['adults'] ) ) )
							)
						) {
							$returned_accoms[ $i ]['people']++;
							unset( $returned_accoms[ count( $returned_accoms ) - 1 ] );
							return $returned_accoms;
						}
					}
					for ( $i = count( $returned_accoms ) - 2; $i >= 0; $i-- ) {
						foreach ( $accom as $a ) {
							if (
								( ( $a['max_occupancy'] - $returned_accoms[ $i ]['people'] ) >= 1 )
								&&
								(
									( ( ! isset( $returned_accoms[ $i ]['children_max_allocated'] ) ) && ( ! isset( $returned_accoms[ $i ]['adults_max_allocated'] ) ) ) ||
									( ( isset( $returned_accoms[ $i ]['children_max_allocated'] ) ) && ( ( isset( $returned_accoms[ $i ]['children'] ) ) && ( $returned_accoms[ $i ]['children_max_allocated'] > $returned_accoms[ $i ]['children'] ) ) ) ||
									( ( isset( $returned_accoms[ $i ]['adults_max_allocated'] ) ) && ( ( isset( $returned_accoms[ $i ]['adults'] ) ) && ( $returned_accoms[ $i ]['adults_max_allocated'] > $returned_accoms[ $i ]['adults'] ) ) )
								)
							) {
								$returned_accoms[ $i ]['id'] = $a['id'];
								$returned_accoms[ $i ]['people']++;
								unset( $returned_accoms[ count( $returned_accoms ) - 1 ] );
								return $returned_accoms;
							}
						}
					}
				}
			} else {
				usort( $accom, function( $a, $b ) {
					if ( $a['occupancy'] == $b['occupancy'] ) {
						return 0;
					}
					return ( $a['occupancy'] < $b['occupancy'] ) ? -1 : 1;
				});
				foreach ( $accom as $a ) {
					if ( count( $returned_accoms ) > 0 ) {
						if ( $a['occupancy'] >= $returned_accoms[ count( $returned_accoms ) - 1 ]['people'] ) {
							$returned_accoms[ count( $returned_accoms ) - 1 ]['id'] = $a['id'];
							return $returned_accoms;
						}
					}
				}
			}
			return $returned_accoms;
		} else if ( $people_left <= $distribution_margin ) {
			return $returned_accoms;
		} else {
			return false;
		}
	}

	private function distribute_children( $accom_distributed, $adults, $children ) {
		if ( ( count( $accom_distributed ) > $adults ) && ( get_option( 'hb_allow_children_only_in_accom' ) == 'no' ) ) {
			return false;
		}
		$adults_left = $adults;
		$children_left = $children;
		$sensitive_distribution = false;
		$sensitive_accom_count = 0;
		$total_max_adults_sensitive = 0;
		$total_max_children_sensitive = 0;

		$accom_reshuffled = $accom_distributed;

		foreach( $accom_reshuffled as $i => $a ) {
			$accom_reshuffled[ $i ]['adults'] = 0;
			$accom_reshuffled[ $i ]['children'] = 0;
			$accom_reshuffled[ $i ]['ids'] = array( $a['id'] );
			if ( isset( $a['adults_max_allocated'] ) ) {
				$sensitive_distribution = true;
				$sensitive_accom_count++;
				$total_max_adults_sensitive += $a['adults_max_allocated'];
				if ( ( $a['adults_max_allocated'] > 0 ) && ( $adults_left > 0 ) ) {
					$accom_reshuffled[ $i ]['adults']++;
					$total_max_adults_sensitive--;
					$adults_left--;
				} else if ( ( $a['adults_max_allocated'] == 0 ) && ( $children_left > 0 ) ) {
					$accom_reshuffled[ $i ]['children']++;
					$children_left--;
				}
			} else if ( isset( $a['children_max_allocated'] ) ) {
				$sensitive_distribution = true;
				$sensitive_accom_count++;
				$total_max_children_sensitive += $a['children_max_allocated'];

				if ( ( $a['children_max_allocated'] > 0 ) && ( $children_left > 0 ) ) {
					$accom_reshuffled[$i]['children']++;
					$total_max_children_sensitive--;
					$children_left--;
					if ( ( $a['people'] > $a['children_max_allocated'] ) && ( $adults_left > 0 ) ) {
						$accom_reshuffled[$i]['adults']++;
						$adults_left--;
					}
				} else if ( ( $a['children_max_allocated'] == 0 ) && ( $adults_left > 0 ) ) {
					$accom_reshuffled[$i]['adults']++;
					$adults_left--;
				}
			} else if ( $adults_left > 0 ) {
				$accom_reshuffled[$i]['adults']++;
				$adults_left--;
			}
		}
		if ( $sensitive_distribution ) {
			$available_accom = $accom_reshuffled;
			$can_fill_sensitive = true;
			$j = 1;
			while ( ( count( $available_accom ) > 0 ) && ( ( ( $adults_left > 0 ) || ( $children_left > 0 )) ) ) {
				if ( 50 == $j ) {
					return false;
				}
				$l = 0;
				foreach( $available_accom as $i => $a ) {
					$l++;
					if ( ( $adults_left <= 0 ) && ( $children_left <= 0 ) ) {
						break;
					}
					if ( $a['people'] > ( $a['adults'] + $a['children'] ) ) {
						if (
							(
								( ( $total_max_adults_sensitive > 0 ) && ( $adults_left > 0 ) ) ||
								( ( $total_max_children_sensitive > 0 ) && ( $children_left > 0 ) )
							) &&
							( $can_fill_sensitive )
						) {
							if ( $l <= count( $available_accom ) ) {
								if ( isset( $a['adults_max_allocated'] ) ) {
									if ( ( $a['adults_max_allocated'] > $a['adults'] ) && ( $adults_left > 0 ) ) {
										$available_accom[$i]['adults']++;
										$accom_reshuffled[$i]['adults']++;
										$total_max_adults_sensitive--;
										$adults_left--;
									}
								} else if ( isset( $a['children_max_allocated'] ) ) {
									if ( ( $a['children_max_allocated'] > $a['children'] ) && ( $children_left > 0 ) ) {
										$available_accom[ $i ]['children']++;
										$accom_reshuffled[ $i ]['children']++;
										$total_max_children_sensitive--;
										$children_left--;
									}
								} else {
									continue;
								}
							} else {
								$can_fill_sensitive = false;
							}
						} else {
							if ( $sensitive_accom_count > 0 ) {
								if ( isset( $a['adults_max_allocated'] ) ) {
									if ( ( $a['adults_max_allocated'] > $a['adults'] ) && ( $adults_left > 0 ) ) {
										$available_accom[$i]['adults']++;
										$accom_reshuffled[$i]['adults']++;
										$total_max_adults_sensitive--;
										$adults_left--;
									} else if ( $children_left > 0 ) {
										$available_accom[ $i ]['children']++;
										$accom_reshuffled[ $i ]['children']++;
										$children_left--;
									}
								} else if ( isset( $a['children_max_allocated'] ) ) {
									if ( ( $a['children_max_allocated'] > $a['children'] ) && ( $children_left > 0 ) ) {
										$available_accom[ $i ]['children']++;
										$accom_reshuffled[ $i ]['children']++;
										$total_max_children_sensitive--;
										$children_left--;
									} else if ( $adults_left > 0 ) {
										$available_accom[ $i ]['adults']++;
										$accom_reshuffled[ $i ]['adults']++;
										$adults_left--;
									}
								} else {
									continue;
								}
							} else {
								if ( ( $adults_left > 0 ) && ( $adults_left >= $children_left ) ) {
									$available_accom[ $i ]['adults']++;
									$accom_reshuffled[ $i ]['adults']++;
									$adults_left--;
								} else if ( $children_left > 0 ) {
									$available_accom[ $i ]['children']++;
									$accom_reshuffled[ $i ]['children']++;
									$children_left--;
								}
							}
						}
						if (
							(
								( $adults_left > 0 ) &&
								( $children_left == 0 ) &&
								( ( isset( $a['adults_max_allocated'] ) ) && ( $a['adults_max_allocated'] == $a['adults'] ) )
							)
						||
							(
								( $adults_left == 0 ) &&
								( $children_left > 0 ) &&
								( ( isset( $a['children_max_allocated'] ) ) && ( $a['children_max_allocated'] == $a['children'] ) )
							)
						) {
							if ( ( isset( $a['adults_max_allocated'] ) ) || ( isset( $a['children_max_allocated'] ) ) ) {
								$sensitive_accom_count--;
							}
							unset( $available_accom[ $i ] );
						} else {
							continue;
						}
					} else {
						if ( ( isset( $a['adults_max_allocated'] ) ) || ( isset( $a['children_max_allocated'] ) ) ) {
							$sensitive_accom_count--;
						}
						unset( $available_accom[ $i ] );
					}
				}
				$j++;
			}
			if ( ( $adults_left > 0 ) || ( $children_left > 0 ) ) {
				return false;
			} else {
				foreach ( $accom_reshuffled as $i => $a ) {
					if ( ( $a['adults'] + $a['children'] ) == 0 ) {
						unset( $accom_reshuffled[ $i ] );
					}
				}
				return $accom_reshuffled;
			}
		} else {
			$children_rate = $children / ( $adults + $children );
			$children_left = $children;
			$adults_left = $adults;
			foreach ( $accom_reshuffled as $i => $a ) {
				if ( $children_left  > 0 ) {
					$children_in_accom = ceil( $a['people'] * $children_rate );
					if ( $adults_left > 0 ) { 
						if ( $children_in_accom >= $a['people'] ) {
							$children_in_accom = $a['people'] - 1;
						}
					} else {
						if ( $children_in_accom >= $a['people'] ) {
							if ( get_option( 'hb_allow_children_only_in_accom' ) == 'no' ) {
								$children_in_accom = $a['people'] - 1;
							} else {
								$children_in_accom = $a['people'];
							}
						}
					}
					if ( $children_in_accom >= $children_left ) {
						$children_in_accom = $children_left;
					}
					$children_left -= $children_in_accom;
				} else {
					$children_in_accom = '0';
				}
				$adults_in_accom = $a['people'] - $children_in_accom;
				$adults_left -= $adults_in_accom;
				$accom_reshuffled[ $i ]['adults'] = $adults_in_accom;
				$accom_reshuffled[ $i ]['children'] = $children_in_accom;
				$accom_reshuffled[ $i ]['ids'] = array( $a['id'] );
			}
			if ( $children_left > 0 ) {
				return false;
			} else {
				return $accom_reshuffled;
			}
		}
	}
}