<?php

//************************************ REST API Code ************************************//
/*
Restrict API Request without the app key
*/
function eimams_custom_check_app_key() {
    $app_key = isset($_SERVER['HTTP_X_APP_KEY']) ? sanitize_text_field($_SERVER['HTTP_X_APP_KEY']) : '';

    $expected_app_key = '3355';
	
	error_log('App Key Received: ' . $app_key);

    if ($app_key !== $expected_app_key) {
        return new WP_REST_Response(['message' => 'Forbidden: Invalid API key.'], 403);
    }

    return true;
}

/*
Get all posts through: /wp-json/wp/v2/postdata
*/
function eimams_custom_register_rest_routes() {
    register_rest_route('wp/v2', '/postdata', [
        'methods' => 'GET',
        'callback' => 'eimams_custom_get_all_posts',
		'permission_callback' => 'eimams_custom_check_app_key',
    ]);
}
add_action('rest_api_init', 'eimams_custom_register_rest_routes');

function eimams_custom_get_all_posts(WP_REST_Request $request) {
    $args = [
        'post_type' => 'post',
        'posts_per_page' => 100,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) {
		$posts = [];
		while ($query->have_posts()) {
			$query->the_post();
			setup_postdata($query->post);

			$posts[] = [
				'id' => get_the_ID(),
				'title' => get_the_title(),
				'link' => get_permalink(),
				'image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
				'description' => get_the_excerpt(),
				'date' => get_the_date(),
			];
		}
		wp_reset_postdata();

		return new WP_REST_Response($posts, 200);
	} else {
		return new WP_REST_Response([], 404);
	}
}


/*
Login: /wp-json/wp/v2/login
*/
function eimams_custom_register_login_route() {
    register_rest_route('wp/v2', '/login', [
        'methods' => 'POST',
        'callback' => 'eimams_custom_login_user',
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
}
add_action('rest_api_init', 'eimams_custom_register_login_route');

function eimams_custom_login_user(WP_REST_Request $request) {
    $username = sanitize_text_field($request->get_param('username'));
    $password = sanitize_text_field($request->get_param('password'));

    if (empty($username) || empty($password)) {
        return new WP_REST_Response(['message' => 'Username and password are required.'], 400);
    }

    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        return new WP_REST_Response(['message' => 'Invalid username or password.'], 401);
    }

    wp_set_auth_cookie($user->ID);

    return new WP_REST_Response([
        'message' => 'Login successful.',
        'user_id' => $user->ID,
        'user_email' => $user->user_email,
        'user_name' => $user->user_login,
    ], 200);
}

/*
Forgot Password: /wp-json/wp/v2/forgot-password
*/
add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/forgot-password', [
        'methods'  => 'POST',
        'callback' => 'custom_forgot_password',
        'args'     => [
            'email' => [
                'required' => true,
                'type'     => 'string',
                'format'   => 'email',
            ],
        ],
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
});

function custom_forgot_password(WP_REST_Request $request) {
    $email = sanitize_email($request->get_param('email'));

    if (empty($email) || !is_email($email)) {
        return new WP_REST_Response(['message' => 'Invalid email address.'], 400);
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_REST_Response(['message' => 'No user found with this email.'], 404);
    }

    $reset_key = get_password_reset_key($user);

    if (is_wp_error($reset_key)) {
        return new WP_REST_Response(['message' => 'Unable to generate reset key.'], 500);
    }

    $reset_url = site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($user->user_login), 'login');

    $subject = 'Password Reset Request';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "You requested a password reset. Click the link below to reset your password:\n\n";
    $message .= $reset_url . "\n\n";
    $message .= "If you did not request this, please ignore this email.";

    $sent = wp_mail($email, $subject, $message);

    if (!$sent) {
        return new WP_REST_Response(['message' => 'Failed to send reset email.'], 500);
    }

    return new WP_REST_Response(['message' => 'Password reset email sent.'], 200);
}

/*
All Jobs: /wp-json/wp/v2/all-jobs
*/
function eimams_register_jobs_rest_route() {
    register_rest_route('wp/v2', '/all-jobs', [
        'methods' => 'GET',
        'callback' => 'eimams_get_all_jobs',
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
}
add_action('rest_api_init', 'eimams_register_jobs_rest_route');

function eimams_get_all_jobs(WP_REST_Request $request) {
    $args = [
        'post_type' => 'job',
        'posts_per_page' => 100,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);

    $jobs = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $job_id = get_the_ID();

            $jobs[] = [
                'id' => $job_id,
                'title' => get_the_title(),
                'description' => get_the_excerpt(),
                'posted_date' => get_the_date(),
                'link' => get_permalink(),
                'employer_image' => get_the_post_thumbnail_url($job_id, 'full'),
            ];
        }
        wp_reset_postdata();

        return new WP_REST_Response($jobs, 200);
    } else {
        return new WP_REST_Response([], 404);
    }
}

/*
Get Single Job: /wp-json/wp/v2/job/{job-permalink or ID}
*/
function eimams_register_single_job_rest_route() {
    register_rest_route('wp/v2', '/job/(?P<id>[a-zA-Z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'eimams_get_single_job',
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
}
add_action('rest_api_init', 'eimams_register_single_job_rest_route');

function eimams_get_single_job(WP_REST_Request $request) {
    $id_or_slug = $request->get_param('id');

    // Try to get post by ID first
    $job = get_post((int)$id_or_slug);

    // If not found by ID or not a job post type, try by slug
    if (!$job || $job->post_type !== 'job') {
        $job = get_page_by_path($id_or_slug, OBJECT, 'job');
    }

    // If still not found, return 404
    if (!$job) {
        return new WP_REST_Response(['message' => 'Job not found'], 404);
    }

    $job_id = $job->ID;

    $job_data = [
        'id' => $job_id,
        'title' => get_the_title($job_id),
        'description' => apply_filters('the_content', $job->post_content),
        'posted_date' => get_the_date('', $job_id),
        'link' => get_permalink($job_id),
        'employer_image' => get_the_post_thumbnail_url($job_id, 'full'),
    ];

    return new WP_REST_Response($job_data, 200);
}


/*
Job Seeker Registration: /wp-json/wp/v2/job-seeker-registration
*/
function eimams_custom_register_job_seeker_route() {
    register_rest_route('wp/v2', '/job-seeker-registration', [
        'methods' => 'POST',
        'callback' => 'eimams_custom_register_job_seeker',
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
}
add_action('rest_api_init', 'eimams_custom_register_job_seeker_route');

function eimams_custom_register_job_seeker(WP_REST_Request $request) {
    $username = sanitize_user($request->get_param('username'));
    $password = sanitize_text_field($request->get_param('password'));
    $email    = sanitize_email($request->get_param('user_email'));

    if (username_exists($username) || email_exists($email)) {
        return new WP_REST_Response(['message' => 'Username or email already exists.'], 409);
    }

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_REST_Response(['message' => 'User registration failed.', 'error' => $user_id->get_error_message()], 400);
    }

    // Set role
    wp_update_user([
        'ID' => $user_id, 
        'role' => 'job_seeker'
    ]);

    // Save full name as display_name
    $full_name = sanitize_text_field($request->get_param('full_name'));
    if (!empty($full_name)) {
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $full_name
        ]);
    }

    // Additional fields to save
    $fields = [
        'phone_number', 'full_name', 'address1', 'address2', 'city', 'state',
        'post_code', 'usr_zone', 'merged_taxonomy', 'usr_madhab', 'usr_aqeeda',
        'usr_qualification', 'job_types', 'usr_yr_of_exp', 'gender',
        'sal_amount', 'sal_period', 'sa_option', 'dbs', 'dbs_info_box',
        'marketing_area', 'term_check'
    ];

    foreach ($fields as $field) {
        $value = sanitize_text_field($request->get_param($field));
        if (!empty($value)) {
            update_user_meta($user_id, $field, $value);
        }
    }

    // Handle CV upload (upload_cv)
    if (isset($_FILES['upload_cv']) && !empty($_FILES['upload_cv']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploaded = media_handle_upload('upload_cv', 0);

        if (is_wp_error($uploaded)) {
            return new WP_REST_Response([
                'message' => 'CV upload failed.',
                'error' => $uploaded->get_error_message()
            ], 400);
        } else {
            update_user_meta($user_id, 'upload_cv', $uploaded);
        }
    }

    return new WP_REST_Response([
        'message' => 'Registration successful.',
        'user_id' => $user_id,
    ], 201);
}

/*
Employer Registration: /wp-json/wp/v2/employer-registration
*/

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/employer-registration', [
        'methods' => 'POST',
        'callback' => 'eimams_custom_register_employer',
        'permission_callback' => 'eimams_custom_check_app_key',
    ]);
});

function eimams_custom_register_employer(WP_REST_Request $request) {
    $username = sanitize_user($request->get_param('UserName'));
    $password = sanitize_text_field($request->get_param('PassWord'));
    $email    = sanitize_email($request->get_param('usr_email'));

    if (username_exists($username) || email_exists($email)) {
        return new WP_REST_Response(['message' => 'Username or email already exists.'], 409);
    }

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_REST_Response(['message' => 'User registration failed.', 'error' => $user_id->get_error_message()], 400);
    }

    // Set role to employer
    wp_update_user([
        'ID' => $user_id,
        'role' => 'employer'
    ]);

    // Save fields as user meta
    $fields = [
        'company_name', 'website', 'rep_name', 'address1', 'address2', 'city',
        'state_pro_reg', 'post_code', 'usr_zone', 'phoneNumber', 'marketing_area'
    ];

    foreach ($fields as $field) {
        $value = sanitize_text_field($request->get_param($field));
        if (!empty($value)) {
            update_user_meta($user_id, $field, $value);
        }
    }

    // Handle company_logo upload
    if (isset($_FILES['company_logo']) && !empty($_FILES['company_logo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploaded = media_handle_upload('company_logo', 0); // 0 = no post attachment

        if (is_wp_error($uploaded)) {
            return new WP_REST_Response([
                'message' => 'Company logo upload failed.',
                'error' => $uploaded->get_error_message()
            ], 400);
        } else {
            update_user_meta($user_id, 'company_logo', $uploaded); // store attachment ID
        }
    }

    return new WP_REST_Response([
        'message' => 'Employer registration successful.',
        'user_id' => $user_id,
    ], 201);
}

/*
Eminent Scholars: /wp-json/wp/v2/eminent-scholars
*/

function eimams_register_eminent_scholars_route() {
    register_rest_route('wp/v2', '/eminent-scholars', [
        'methods' => 'GET',
        'callback' => 'eimams_get_eminent_scholars',
        'permission_callback' => 'eimams_custom_check_app_key', 
    ]);
}
add_action('rest_api_init', 'eimams_register_eminent_scholars_route');

function eimams_get_eminent_scholars(WP_REST_Request $request) {
    $args = [
        'post_type' => 'e_scholars',
        'posts_per_page' => 100,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $scholars = [];

        while ($query->have_posts()) {
            $query->the_post();
            setup_postdata($query->post);

            $scholars[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'link' => get_permalink(),
                'image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
                'date' => get_the_date(),
            ];
        }

        wp_reset_postdata();
        return new WP_REST_Response($scholars, 200);
    } else {
        return new WP_REST_Response([], 404);
    }
}

/*
Featured Jobs: /wp-json/wp/v2/featuredjobs
*/






?>
