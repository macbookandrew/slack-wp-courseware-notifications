<?php
/*
Plugin Name: Slack WP Courseware Notifications
Plugin URI: http://andrewrminion.com/2016/02/slack-wp-courseware-notifications/
Description: Adds Slack notifications for WP Courseware events
Version: 1.0
Author: AndrewRMinion Design
Author URI: https://andrewrminion.com
*/

// add callback for WP Courseware quizzes
function wp_slack_wp_courseware_quizzes( $events ) {
    // individual unit completion
    $events['wpcw_user_completed_unit'] = array(
        'action' => 'wpcw_user_completed_unit',
        'description' => __( 'When a user completes a unit', 'slack' ),
        'message' => function( $user_id, $post_id, $unit_object ) {

            // get user name
            $user = get_user_by( 'ID', $user_id );
            if ( $user->first_name && $user->last_name ) {
                $user_name = $user->first_name . ' ' . $user->last_name;
            } else {
                $user_name = $user->data->user_nicename;
            }

            return sprintf(
                'Unit completed by *%1$s*: <%2$s|%3$s>',
                $user_name,
                get_permalink( $post_id ),
                wp_slack_wp_courseware_module_info( $post_id )->course_title . ' / ' . wp_slack_wp_courseware_module_info( $post_id )->module_title . ' / ' . get_the_title( $post_id )
            );
        }
    );

    // module completion
    $events['wpcw_user_completed_module'] = array(
        'action' => 'wpcw_user_completed_module',
        'description' => __( 'When a user completes a module', 'slack' ),
        'message' => function( $user_id, $post_id, $unit_object ) {

            // get user name
            $user = get_user_by( 'ID', $user_id );
            if ( $user->first_name && $user->last_name ) {
                $user_name = $user->first_name . ' ' . $user->last_name;
            } else {
                $user_name = $user->data->user_nicename;
            }

            return sprintf(
                'Module completed by *%1$s*: <%2$s|%3$s>',
                $user_name,
                get_permalink( $post_id ),
                wp_slack_wp_courseware_module_info( $post_id )->course_title . ' / ' . wp_slack_wp_courseware_module_info( $post_id )->module_title
            );
        }
    );

    // course completion
    $events['wpcw_user_completed_course'] = array(
        'action' => 'wpcw_user_completed_course',
        'description' => __( 'When a user completes a course', 'slack' ),
        'message' => function( $user_id, $post_id, $unit_object ) {
            $quiz_string = NULL;

            // get user name
            $user = get_user_by( 'ID', $user_id );
            if ( $user->first_name && $user->last_name ) {
                $user_name = $user->first_name . ' ' . $user->last_name;
            } else {
                $user_name = $user->data->user_nicename;
            }

            // output user info
            $quiz_string = sprintf(
                'Course completed by *%1$s*: <%2$s|%3$s>' . "\n",
                $user_name,
                get_permalink( $post_id ),
                wp_slack_wp_courseware_module_info( $post_id )->course_title
            );

            // get quiz info
            $quiz_answers = maybe_unserialize( get_quiz_responses( $post_id, $user_id )->quiz_data );
            foreach ( $quiz_answers as $answer ) {
                $quiz_string .= sprintf(
                    '*%1$s* ' . "\n" . '>%2$s' . "\n",
                    $answer['title'],
                    str_replace( '&#13;&#10;', "\n" . '>', $answer['their_answer'] )
                );
            }

            $quiz_string .= sprintf(
                '<mailto:%1$s?subject=%2$s course on %3$s|Reply via email>',
                $user->user_email,
                wp_slack_wp_courseware_module_info( $post_id )->course_title,
                get_bloginfo( 'name' )
            );

            return $quiz_string;
        }
    );

    return $events;
}
add_filter( 'slack_get_events', 'wp_slack_wp_courseware_quizzes' );

// get parent info
function wp_slack_wp_courseware_module_info( $post_id ) {
    global $wpdb;

    $SQL = $wpdb->prepare(
        'SELECT *
        FROM ' . $wpdb->prefix . 'wpcw_units_meta um
        LEFT JOIN ' . $wpdb->prefix . 'wpcw_modules m ON m.module_id = um.parent_module_id
        LEFT JOIN ' . $wpdb->prefix . 'wpcw_courses c ON c.course_id = m.parent_course_id
        WHERE um.unit_id = %d AND course_title IS NOT NULL',
        $post_id
    );

    return $wpdb->get_row( $SQL );
}

// get quiz responses
function get_quiz_responses( $post_id, $user_id ) {
    global $wpdb;

    $SQL = $wpdb->prepare(
        'SELECT *
        FROM ' . $wpdb->prefix . 'wpcw_user_progress_quizzes quiz
        WHERE quiz.user_id = %d
        AND unit_id = %d',
        $user_id,
        $post_id
    );

    return $wpdb->get_row( $SQL );
}
