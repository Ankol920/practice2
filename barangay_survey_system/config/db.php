<?php
/**
 * Database connection.
 * Uses the default XAMPP MySQL/MariaDB settings so the app connects reliably on local development setups.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barangay_survey_system');
define('DB_PORT', 3306);

// Live hosting values. The password is deliberately NOT stored in source
// control - paste it in only on the deployed copy, never in the zip you
// hand in or push.
// define('DB_HOST', 'sql208.infinityfree.com');
// define('DB_USER', 'if0_42582557');
// define('DB_PASS', 'PASTE_ON_SERVER_ONLY');
// define('DB_NAME', 'if0_42582557_leonardogroup3');
// define('DB_PORT', 3307);†

/**
 * Philippine time, everywhere. Without this, PHP falls back to the host's
 * default - UTC on most shared hosting - and every date shown to a user
 * runs up to a day behind local time.
 */
date_default_timezone_set('Asia/Manila');

/**
 * Connect, creating the database first if it is not there.
 *
 * Connecting straight to DB_NAME throws "Unknown database" the moment the
 * schema is dropped, which leaves a blank fatal-error page and no way back
 * in. Connecting to the server first, then issuing CREATE DATABASE IF NOT
 * EXISTS, means a missing database rebuilds itself on the next page load -
 * the same principle as the column checks further down.
 */
/**
 * The try/catch is not optional on PHP 8.
 *
 * mysqli reports errors by throwing since 8.0, and the @ operator does not
 * suppress exceptions. Written as `@new mysqli(...)` followed by a check on
 * $conn->connect_error, the check can never run: an unreachable database
 * throws straight past it and the visitor gets an uncaught fatal error with
 * a stack trace of server paths. Catching it is what actually produces the
 * intended $conn = null and a page that can explain itself.
 */
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    $conn = null;
}

if ($conn === null) {
    // Nothing below can run without a connection.
} elseif ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    $conn = null;
} else {
    $conn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

    if (!$conn->select_db(DB_NAME)) {
        error_log('Could not select database ' . DB_NAME . ': ' . $conn->error);
        $conn = null;
        return;
    }

    $conn->set_charset('utf8mb4');

    // Keep MySQL on the same clock as PHP, so NOW() in a login record or a
    // reviewed_at stamp matches the date() calls that render it.
    $conn->query("SET time_zone = '+08:00'");

    /**
     * Base tables.
     *
     * These are the eight tables the original SQL dump creates. They are
     * repeated here so a brand-new, empty database becomes a working system
     * on the first page load - without them, every ALTER below would fail
     * against tables that do not exist yet. IF NOT EXISTS means an existing
     * installation is untouched.
     */
    $base_tables = [
        'staff' => "CREATE TABLE IF NOT EXISTS staff (
            staff_id int(11) NOT NULL AUTO_INCREMENT,
            username varchar(50) NOT NULL,
            full_name varchar(100) NOT NULL,
            password varchar(255) NOT NULL,
            role varchar(30) NOT NULL DEFAULT 'staff',
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (staff_id),
            UNIQUE KEY username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'residents' => "CREATE TABLE IF NOT EXISTS residents (
            resident_id int(11) NOT NULL AUTO_INCREMENT,
            resident_number varchar(20) NOT NULL,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            email varchar(100) DEFAULT NULL,
            contact_number varchar(20) DEFAULT NULL,
            birthdate date DEFAULT NULL,
            address varchar(255) DEFAULT NULL,
            password varchar(255) NOT NULL,
            is_first_login tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (resident_id),
            UNIQUE KEY resident_number (resident_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'surveys' => "CREATE TABLE IF NOT EXISTS surveys (
            survey_id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(150) NOT NULL,
            description text DEFAULT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_by int(11) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (survey_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'survey_questions' => "CREATE TABLE IF NOT EXISTS survey_questions (
            question_id int(11) NOT NULL AUTO_INCREMENT,
            survey_id int(11) NOT NULL,
            question_text varchar(255) NOT NULL,
            question_type enum('multiple_choice','yesno','rating','short_answer') NOT NULL,
            question_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (question_id),
            KEY survey_id (survey_id),
            CONSTRAINT fk_question_survey FOREIGN KEY (survey_id)
                REFERENCES surveys (survey_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'survey_choices' => "CREATE TABLE IF NOT EXISTS survey_choices (
            choice_id int(11) NOT NULL AUTO_INCREMENT,
            question_id int(11) NOT NULL,
            choice_text varchar(150) NOT NULL,
            PRIMARY KEY (choice_id),
            KEY question_id (question_id),
            CONSTRAINT fk_choice_question FOREIGN KEY (question_id)
                REFERENCES survey_questions (question_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'responses' => "CREATE TABLE IF NOT EXISTS responses (
            response_id int(11) NOT NULL AUTO_INCREMENT,
            survey_id int(11) NOT NULL,
            resident_id int(11) NOT NULL,
            submitted_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (response_id),
            KEY survey_id (survey_id),
            KEY resident_id (resident_id),
            CONSTRAINT fk_response_survey FOREIGN KEY (survey_id)
                REFERENCES surveys (survey_id) ON DELETE CASCADE,
            CONSTRAINT fk_response_resident FOREIGN KEY (resident_id)
                REFERENCES residents (resident_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'survey_results' => "CREATE TABLE IF NOT EXISTS survey_results (
            answer_id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            choice_id int(11) DEFAULT NULL,
            rating_value int(11) DEFAULT NULL,
            answer_text text DEFAULT NULL,
            PRIMARY KEY (answer_id),
            KEY response_id (response_id),
            KEY question_id (question_id),
            CONSTRAINT fk_result_response FOREIGN KEY (response_id)
                REFERENCES responses (response_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'login_history' => "CREATE TABLE IF NOT EXISTS login_history (
            log_id int(11) NOT NULL AUTO_INCREMENT,
            user_type enum('resident','staff') NOT NULL,
            user_id int(11) NOT NULL,
            login_time timestamp NOT NULL DEFAULT current_timestamp(),
            ip_address varchar(50) DEFAULT NULL,
            PRIMARY KEY (log_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    foreach ($base_tables as $create_sql) {
        $conn->query($create_sql);
    }

    /**
     * First administrator.
     *
     * Only inserted when the staff table is completely empty, which is the
     * case on a fresh database. Without it there is no way to sign in and
     * no way to create an account, since only an admin can add staff.
     * Username: admin / Password: admin123 - change it after signing in.
     */
    $has_staff = $conn->query("SELECT staff_id FROM staff LIMIT 1");
    if ($has_staff && $has_staff->num_rows === 0) {
        $seed_admin = $conn->prepare("INSERT INTO staff (username, full_name, password, role) VALUES (?, ?, ?, 'admin')");
        $seed_username = 'admin';
        $seed_name = 'Barangay Health Officer';
        $seed_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $seed_admin->bind_param('sss', $seed_username, $seed_name, $seed_hash);
        $seed_admin->execute();
        $seed_admin->close();
    }

    /**
     * Self-healing schema checks. Each one adds a column only if it is
     * missing, so the app runs against an older dump without the user
     * having to import anything by hand.
     */
    $auto_columns = [
        ['residents', 'birthdate',        "ALTER TABLE residents ADD COLUMN birthdate date DEFAULT NULL AFTER contact_number"],
        ['residents', 'residency_status', "ALTER TABLE residents ADD COLUMN residency_status varchar(20) NOT NULL DEFAULT 'permanent' AFTER address"],
        ['residents', 'gender',           "ALTER TABLE residents ADD COLUMN gender varchar(10) DEFAULT NULL AFTER birthdate"],
        ['residents', 'middle_name',      "ALTER TABLE residents ADD COLUMN middle_name varchar(50) DEFAULT NULL AFTER first_name"],
        ['residents', 'name_extension',   "ALTER TABLE residents ADD COLUMN name_extension varchar(20) DEFAULT NULL AFTER last_name"],
        ['residents', 'photo_path',       "ALTER TABLE residents ADD COLUMN photo_path varchar(255) DEFAULT NULL AFTER address"],
        // Sign-outs share the login_history table: same shape, same joins.
        // Existing rows default to 'login', which is what they all are.
        ['login_history', 'event', "ALTER TABLE login_history ADD COLUMN event varchar(10) NOT NULL DEFAULT 'login'"],
        // Residency at the moment of the event, so the Administration panels
        // keep reading correctly even after someone is promoted later.
        ['login_history', 'account_role', "ALTER TABLE login_history ADD COLUMN account_role varchar(20) DEFAULT NULL"],
        // Supporting reference for the special category: PWD ID, Senior
        // Citizen ID, or the doctor's PRC licence on the medical certificate.
        // One column, because a resident holds one category at a time.
        ['residents', 'category_reference', "ALTER TABLE residents ADD COLUMN category_reference varchar(60) DEFAULT NULL"],

        /* ------------------------------------------------------------------
           Household and family details.

           Occupation is free text rather than a fixed list: "Student",
           "Tricycle driver" and "OFW - seafarer" all have to fit, and a
           dropdown would either miss them or need endless maintenance.

           The hotline is optional and separate from the personal contact
           number — it is the landline the household can be reached on.

           Spouse and parents are one row each, so they live here as columns.
           Children and character references can repeat, so they get their
           own tables further down.
           ------------------------------------------------------------------ */
        ['residents', 'occupation',      "ALTER TABLE residents ADD COLUMN occupation varchar(100) DEFAULT NULL"],
        ['residents', 'hotline_number',  "ALTER TABLE residents ADD COLUMN hotline_number varchar(30) DEFAULT NULL"],

        // 0 = not married, so no spouse name is expected or shown.
        ['residents', 'is_married',            "ALTER TABLE residents ADD COLUMN is_married tinyint(1) NOT NULL DEFAULT 0"],
        ['residents', 'spouse_last_name',      "ALTER TABLE residents ADD COLUMN spouse_last_name varchar(50) DEFAULT NULL"],
        ['residents', 'spouse_first_name',     "ALTER TABLE residents ADD COLUMN spouse_first_name varchar(50) DEFAULT NULL"],
        ['residents', 'spouse_middle_name',    "ALTER TABLE residents ADD COLUMN spouse_middle_name varchar(50) DEFAULT NULL"],
        ['residents', 'spouse_name_extension', "ALTER TABLE residents ADD COLUMN spouse_name_extension varchar(20) DEFAULT NULL"],

        // Mirrors the answer to "does this resident have children", so an
        // explicit No is remembered rather than looking like an empty form.
        ['residents', 'has_children',          "ALTER TABLE residents ADD COLUMN has_children tinyint(1) NOT NULL DEFAULT 0"],

        ['residents', 'father_last_name',      "ALTER TABLE residents ADD COLUMN father_last_name varchar(50) DEFAULT NULL"],
        ['residents', 'father_first_name',     "ALTER TABLE residents ADD COLUMN father_first_name varchar(50) DEFAULT NULL"],
        ['residents', 'father_middle_name',    "ALTER TABLE residents ADD COLUMN father_middle_name varchar(50) DEFAULT NULL"],
        ['residents', 'father_name_extension', "ALTER TABLE residents ADD COLUMN father_name_extension varchar(20) DEFAULT NULL"],
        ['residents', 'mother_last_name',      "ALTER TABLE residents ADD COLUMN mother_last_name varchar(50) DEFAULT NULL"],
        ['residents', 'mother_first_name',     "ALTER TABLE residents ADD COLUMN mother_first_name varchar(50) DEFAULT NULL"],
        ['residents', 'mother_middle_name',    "ALTER TABLE residents ADD COLUMN mother_middle_name varchar(50) DEFAULT NULL"],
        ['residents', 'mother_name_extension', "ALTER TABLE residents ADD COLUMN mother_name_extension varchar(20) DEFAULT NULL"],

        /* A spouse or a parent is recorded the same way the resident is:
           courtesy title and gender alongside the four name parts, so the
           profile can show them in the same seven rows as Profile Overview
           rather than as a bare line of text. */
        ['residents', 'spouse_courtesy_title', "ALTER TABLE residents ADD COLUMN spouse_courtesy_title varchar(20) DEFAULT NULL"],
        ['residents', 'spouse_gender',         "ALTER TABLE residents ADD COLUMN spouse_gender varchar(20) DEFAULT NULL"],
        ['residents', 'father_courtesy_title', "ALTER TABLE residents ADD COLUMN father_courtesy_title varchar(20) DEFAULT NULL"],
        ['residents', 'father_gender',         "ALTER TABLE residents ADD COLUMN father_gender varchar(20) DEFAULT NULL"],
        ['residents', 'mother_courtesy_title', "ALTER TABLE residents ADD COLUMN mother_courtesy_title varchar(20) DEFAULT NULL"],
        ['residents', 'mother_gender',         "ALTER TABLE residents ADD COLUMN mother_gender varchar(20) DEFAULT NULL"],

        ['residents', 'birthplace',       "ALTER TABLE residents ADD COLUMN birthplace varchar(150) DEFAULT NULL"],
        // Date the person began residing in the barangay. Permanent status
        // requires a minimum length of stay, checked against this.
        ['residents', 'residency_start_date', "ALTER TABLE residents ADD COLUMN residency_start_date date DEFAULT NULL AFTER residency_status"],
        ['residents', 'special_category', "ALTER TABLE residents ADD COLUMN special_category varchar(20) NOT NULL DEFAULT 'none'"],
        ['residents', 'is_active',        "ALTER TABLE residents ADD COLUMN is_active tinyint(1) NOT NULL DEFAULT 1"],
        ['staff',     'is_active',        "ALTER TABLE staff ADD COLUMN is_active tinyint(1) NOT NULL DEFAULT 1"],

        /**
         * Staff employment record + onboarding state.
         *
         * The two flags default to "already onboarded" on purpose. Adding a
         * column applies its default to every existing row, so a default of
         * is_first_login = 1 would lock the current admin out of their own
         * system on the next page load. New accounts get the pending values
         * written explicitly by the INSERT in user_management.php.
         */
        ['staff',     'staff_number',      "ALTER TABLE staff ADD COLUMN staff_number varchar(20) DEFAULT NULL AFTER staff_id"],
        ['staff',     'first_name',        "ALTER TABLE staff ADD COLUMN first_name varchar(50) DEFAULT NULL AFTER full_name"],
        ['staff',     'middle_name',       "ALTER TABLE staff ADD COLUMN middle_name varchar(50) DEFAULT NULL AFTER first_name"],
        ['staff',     'last_name',         "ALTER TABLE staff ADD COLUMN last_name varchar(50) DEFAULT NULL AFTER middle_name"],
        ['staff',     'name_extension',    "ALTER TABLE staff ADD COLUMN name_extension varchar(20) DEFAULT NULL AFTER last_name"],
        ['staff',     'position',          "ALTER TABLE staff ADD COLUMN position varchar(100) DEFAULT NULL"],
        ['staff',     'date_hired',        "ALTER TABLE staff ADD COLUMN date_hired date DEFAULT NULL"],
        ['staff',     'contact_number',    "ALTER TABLE staff ADD COLUMN contact_number varchar(20) DEFAULT NULL"],
        ['staff',     'email',             "ALTER TABLE staff ADD COLUMN email varchar(100) DEFAULT NULL"],
        ['staff',     'birthdate',         "ALTER TABLE staff ADD COLUMN birthdate date DEFAULT NULL"],
        // Optional professional licence, e.g. PRC for a midwife or nurse, CSE
        // for a civil service eligible. Long enough for a number with its
        // board prefix, and nullable because plenty of staff have neither.
        ['staff',     'license_number',    "ALTER TABLE staff ADD COLUMN license_number varchar(50) DEFAULT NULL AFTER birthdate"],
        ['staff',     'gender',            "ALTER TABLE staff ADD COLUMN gender varchar(10) DEFAULT NULL"],
        ['staff',     'address',           "ALTER TABLE staff ADD COLUMN address varchar(255) DEFAULT NULL"],
        ['staff',     'emergency_contact', "ALTER TABLE staff ADD COLUMN emergency_contact varchar(150) DEFAULT NULL"],

        // Emergency contact split into who to call and what to dial. One
        // free-text line could not be dialled or searched reliably.
        ['staff',     'emergency_contact_name',   "ALTER TABLE staff ADD COLUMN emergency_contact_name varchar(100) DEFAULT NULL"],
        ['staff',     'emergency_contact_number', "ALTER TABLE staff ADD COLUMN emergency_contact_number varchar(20) DEFAULT NULL"],
        ['staff',     'photo_path',        "ALTER TABLE staff ADD COLUMN photo_path varchar(255) DEFAULT NULL"],

        /**
         * Permanent address, kept in parts rather than one free-text line.
         *
         * A staff member's address change has to be reviewable by an
         * administrator, and you cannot review a single string without
         * guessing which part actually moved. The assembled line is still
         * written to `address` so existing displays keep working.
         *
         * The part names match the resident side exactly, so one set of
         * helpers in config/functions.php serves both.
         */
        ['staff',     'household_number',  "ALTER TABLE staff ADD COLUMN household_number varchar(50) DEFAULT NULL"],
        ['staff',     'purok',             "ALTER TABLE staff ADD COLUMN purok varchar(100) DEFAULT NULL"],
        ['staff',     'street_highway',    "ALTER TABLE staff ADD COLUMN street_highway varchar(150) DEFAULT NULL"],
        ['staff',     'barangay',          "ALTER TABLE staff ADD COLUMN barangay varchar(100) DEFAULT NULL"],
        ['staff',     'municipality',      "ALTER TABLE staff ADD COLUMN municipality varchar(100) DEFAULT NULL"],
        ['staff',     'postal_code',       "ALTER TABLE staff ADD COLUMN postal_code varchar(10) DEFAULT NULL"],
        ['staff',     'province',          "ALTER TABLE staff ADD COLUMN province varchar(100) DEFAULT NULL"],
        ['staff',     'courtesy_title',    "ALTER TABLE staff ADD COLUMN courtesy_title varchar(20) DEFAULT NULL AFTER full_name"],
        ['staff',     'is_first_login',    "ALTER TABLE staff ADD COLUMN is_first_login tinyint(1) NOT NULL DEFAULT 0"],
        ['staff',     'profile_completed', "ALTER TABLE staff ADD COLUMN profile_completed tinyint(1) NOT NULL DEFAULT 1"],
        ['staff',     'created_by',        "ALTER TABLE staff ADD COLUMN created_by int(11) DEFAULT NULL"],
    ];

    foreach ($auto_columns as $column) {
        list($table, $name, $sql) = $column;
        try {
            $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$name'");
            if ($check && $check->num_rows === 0) {
                $conn->query($sql);
            }
        } catch (mysqli_sql_exception $e) {
            // Log and carry on: a single column that cannot be added must
            // not take the whole site down with a fatal error page.
            error_log("Schema check failed for $table.$name: " . $e->getMessage());
        }
    }

    /**
     * One response per resident per survey, enforced by the database.
     *
     * submit.php already checks for an existing response before inserting,
     * but that is a SELECT followed by an INSERT: two requests arriving
     * together — a double-clicked Submit button, or a refresh on a slow
     * connection — can both pass the check and both insert. The unique key
     * is what actually makes it impossible, and it is what "answer surveys
     * only once" requires.
     *
     * Any duplicates already recorded are collapsed to the earliest response
     * first, otherwise adding the index fails and the site is left without
     * the guarantee. The oldest row is kept because it is the submission the
     * resident intended; the later ones are the accidental repeats.
     */
    try {
        $dupe_index = $conn->query("SHOW INDEX FROM responses WHERE Key_name = 'uniq_response_per_resident'");
        if ($dupe_index && $dupe_index->num_rows === 0) {
            $conn->query("DELETE r FROM responses r
                          INNER JOIN (
                              SELECT survey_id, resident_id, MIN(response_id) AS keep_id
                              FROM responses
                              GROUP BY survey_id, resident_id
                              HAVING COUNT(*) > 1
                          ) d
                            ON d.survey_id = r.survey_id
                           AND d.resident_id = r.resident_id
                          WHERE r.response_id > d.keep_id");
            $conn->query("ALTER TABLE responses
                          ADD UNIQUE KEY uniq_response_per_resident (survey_id, resident_id)");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Could not add the unique response index: ' . $e->getMessage());
    }

    /**
     * Children and character references, one row each.
     *
     * Both can repeat, so neither fits in a column on residents. Storing them
     * as numbered columns would cap how many can be recorded and leave most of
     * them empty; storing them as one comma-joined string is what caused the
     * address bug earlier in this project, where a name containing a comma
     * could not be told apart from two names.
     *
     * `display_order` keeps them in the order they were entered, so the first
     * character reference stays first on the profile.
     *
     * ON DELETE CASCADE means removing a resident takes their children and
     * references with them rather than leaving rows pointing at nobody.
     */
    /* Same treatment as resident_family_requests below: the foreign key is
       left unnamed so InnoDB cannot collide with a name left behind by an
       earlier table, and a failure to create with the key falls back to
       creating without it rather than taking the site down. */
    $person_row_columns = "
            resident_id    int(11) NOT NULL,
            last_name      varchar(50) DEFAULT NULL,
            first_name     varchar(50) DEFAULT NULL,
            middle_name    varchar(50) DEFAULT NULL,
            name_extension varchar(20) DEFAULT NULL,
            display_order  int(11) NOT NULL DEFAULT 0,
            created_at     timestamp NOT NULL DEFAULT current_timestamp()";

    $person_row_tables = [
        'resident_children'             => 'child_id',
        'resident_character_references' => 'reference_id',
    ];

    foreach ($person_row_tables as $person_table => $key_column) {
        $body = "{$key_column} int(11) NOT NULL AUTO_INCREMENT,
            {$person_row_columns},
            PRIMARY KEY ({$key_column}),
            KEY resident_id (resident_id)";

        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS {$person_table} (
                    {$body},
                    FOREIGN KEY (resident_id) REFERENCES residents (resident_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (mysqli_sql_exception $e) {
            error_log("{$person_table} could not be created with its foreign key: " . $e->getMessage());
            try {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS {$person_table} (
                        {$body}
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (mysqli_sql_exception $inner) {
                error_log("{$person_table} could not be created at all: " . $inner->getMessage());
            }
        }
    }

    /**
     * Family and household changes a resident has asked for.
     *
     * Residents cannot edit these details directly — the same rule the name
     * and address request flow already follows. A resident submits what they
     * believe is correct, staff or an administrator decide, and only an
     * approval writes anything to the record.
     *
     * The proposed values are held as JSON rather than as a mirror of every
     * family column. Children and character references repeat, so a column
     * mirror would need its own child tables and would have to be extended
     * again for every field added later; the payload is written and read by
     * one pair of functions and rendered for review server-side.
     */
    /*
     * Created without naming the foreign key, and without failing the page if
     * it cannot be created at all.
     *
     * InnoDB requires foreign key names to be unique across the whole
     * database, not just the table, and a name can outlive the table it
     * belonged to — a half-finished CREATE, or a table dropped while the
     * server was busy, leaves the name claimed. Asking for it again then
     * fails with errno 121, "Duplicate key on write or update", which took
     * the whole site down on the next page load. Leaving the constraint
     * unnamed lets InnoDB pick a name it knows is free.
     *
     * If it still cannot be created with the key, the table is created
     * without it. The cascade is a convenience for tidying up after a deleted
     * resident, and deletions already go through code that clears these rows;
     * a working site without the cascade beats a fatal error with it.
     */
    $family_requests_columns = "
            request_id       int(11) NOT NULL AUTO_INCREMENT,
            resident_id      int(11) NOT NULL,
            payload          longtext NOT NULL,
            reason           varchar(255) DEFAULT NULL,
            status           varchar(20) NOT NULL DEFAULT 'pending',
            requested_at     timestamp NOT NULL DEFAULT current_timestamp(),
            reviewed_at      datetime DEFAULT NULL,
            reviewed_by      int(11) DEFAULT NULL,
            reviewed_by_name varchar(100) DEFAULT NULL,
            review_comment   varchar(255) DEFAULT NULL,
            PRIMARY KEY (request_id),
            KEY resident_id (resident_id),
            KEY status (status)";

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS resident_family_requests (
                {$family_requests_columns},
                FOREIGN KEY (resident_id) REFERENCES residents (resident_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (mysqli_sql_exception $e) {
        error_log('resident_family_requests could not be created with its foreign key: ' . $e->getMessage());
        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS resident_family_requests (
                    {$family_requests_columns}
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (mysqli_sql_exception $inner) {
            error_log('resident_family_requests could not be created at all: ' . $inner->getMessage());
        }
    }

    /**
     * Record of every move between temporary and permanent residency.
     *
     * When a temporary resident is made permanent, the address they were
     * staying at becomes their permanent one and the old permanent address
     * is archived here. That archive is what makes the move reversible: if
     * the claim later turns out to be false, the admin can put both
     * addresses back exactly as they were.
     */
    $residency_log = $conn->query("SHOW TABLES LIKE 'resident_residency_changes'");
    if ($residency_log && $residency_log->num_rows === 0) {
        $conn->query("CREATE TABLE resident_residency_changes (
            change_id int(11) NOT NULL AUTO_INCREMENT,
            resident_id int(11) NOT NULL,
            from_status varchar(20) NOT NULL,
            to_status varchar(20) NOT NULL,
            reason text DEFAULT NULL,
            changed_by int(11) DEFAULT NULL,
            changed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_reverted tinyint(1) NOT NULL DEFAULT 0,

            -- the permanent address as it stood before this change
            prev_address varchar(255) DEFAULT NULL,
            prev_household_number varchar(50) DEFAULT NULL,
            prev_purok varchar(100) DEFAULT NULL,
            prev_street_highway varchar(150) DEFAULT NULL,
            prev_barangay varchar(100) DEFAULT NULL,
            prev_municipality varchar(100) DEFAULT NULL,
            prev_postal_code varchar(10) DEFAULT NULL,
            prev_province varchar(100) DEFAULT NULL,

            -- the temporary address as it stood before this change
            prev_temporary_address varchar(255) DEFAULT NULL,
            prev_temp_household_number varchar(50) DEFAULT NULL,
            prev_temp_purok varchar(100) DEFAULT NULL,
            prev_temp_street_highway varchar(150) DEFAULT NULL,
            prev_temp_barangay varchar(100) DEFAULT NULL,
            prev_temp_municipality varchar(100) DEFAULT NULL,
            prev_temp_postal_code varchar(10) DEFAULT NULL,
            prev_temp_province varchar(100) DEFAULT NULL,
            prev_residence_type varchar(50) DEFAULT NULL,

            PRIMARY KEY (change_id),
            KEY resident_id (resident_id),
            KEY changed_at (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $requests_table = $conn->query("SHOW TABLES LIKE 'resident_update_requests'");
    if ($requests_table && $requests_table->num_rows === 0) {
        $conn->query("CREATE TABLE resident_update_requests (
            request_id int(11) NOT NULL AUTO_INCREMENT,
            resident_id int(11) NOT NULL,
            first_name varchar(50) DEFAULT NULL,
            last_name varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            contact_number varchar(20) DEFAULT NULL,
            address varchar(255) DEFAULT NULL,
            household_number varchar(50) DEFAULT NULL,
            barangay_subdivision varchar(100) DEFAULT NULL,
            municipality varchar(100) DEFAULT NULL,
            province varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            submitted_at timestamp NOT NULL DEFAULT current_timestamp(),
            reviewed_at datetime DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            review_comment varchar(255) DEFAULT NULL,
            PRIMARY KEY (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /**
     * Resident deletion requests.
     *
     * Deleting a resident destroys their survey responses through the
     * foreign-key cascade, so the action is reserved for an administrator.
     * Staff raise a request here instead, and the row keeps a snapshot of
     * the resident's number and name so the audit trail survives the
     * deletion it authorised.
     */
    $delete_requests_table = $conn->query("SHOW TABLES LIKE 'resident_delete_requests'");
    if ($delete_requests_table && $delete_requests_table->num_rows === 0) {
        $conn->query("CREATE TABLE resident_delete_requests (
            request_id int(11) NOT NULL AUTO_INCREMENT,
            resident_id int(11) NOT NULL,
            resident_number varchar(20) DEFAULT NULL,
            resident_name varchar(120) DEFAULT NULL,
            reason varchar(255) NOT NULL,
            requested_by int(11) DEFAULT NULL,
            requested_by_name varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at timestamp NOT NULL DEFAULT current_timestamp(),
            reviewed_at datetime DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            reviewed_by_name varchar(100) DEFAULT NULL,
            review_comment varchar(255) DEFAULT NULL,
            PRIMARY KEY (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /**
     * Staff address change requests.
     *
     * A staff member's permanent address is on file for employment records,
     * so a change is reviewed rather than self-served. The first entry made
     * during onboarding writes straight through; every later change comes
     * here and waits for an administrator.
     */
    $staff_requests_table = $conn->query("SHOW TABLES LIKE 'staff_update_requests'");
    if ($staff_requests_table && $staff_requests_table->num_rows === 0) {
        $conn->query("CREATE TABLE staff_update_requests (
            request_id int(11) NOT NULL AUTO_INCREMENT,
            staff_id int(11) NOT NULL,
            staff_number varchar(20) DEFAULT NULL,
            staff_name varchar(120) DEFAULT NULL,
            household_number varchar(50) DEFAULT NULL,
            purok varchar(100) DEFAULT NULL,
            street_highway varchar(150) DEFAULT NULL,
            barangay varchar(100) DEFAULT NULL,
            municipality varchar(100) DEFAULT NULL,
            postal_code varchar(10) DEFAULT NULL,
            province varchar(100) DEFAULT NULL,
            address varchar(255) DEFAULT NULL,
            previous_address varchar(255) DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            submitted_at timestamp NOT NULL DEFAULT current_timestamp(),
            reviewed_at datetime DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            reviewed_by_name varchar(100) DEFAULT NULL,
            review_comment varchar(255) DEFAULT NULL,
            PRIMARY KEY (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /**
     * Backfill a Staff Number for accounts that predate the column, so the
     * User Management table never shows an empty identifier.
     */
    $needs_number = $conn->query("SELECT staff_id FROM staff WHERE staff_number IS NULL OR staff_number = '' ORDER BY staff_id");
    if ($needs_number && $needs_number->num_rows > 0) {
        $seed = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(staff_number, '-', -1) AS UNSIGNED)) AS max_seq FROM staff WHERE staff_number LIKE 'S-%'");
        $seed_row = $seed ? $seed->fetch_assoc() : null;
        $next_seq = (int)($seed_row['max_seq'] ?? 0);
        $year = date('Y');

        $assign = $conn->prepare("UPDATE staff SET staff_number = ? WHERE staff_id = ?");
        while ($legacy = $needs_number->fetch_assoc()) {
            $next_seq++;
            $number = 'S-' . $year . '-' . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
            $assign->bind_param('si', $number, $legacy['staff_id']);
            $assign->execute();
        }
        $assign->close();
    }

    $auto_columns = array_merge($auto_columns, [
        /**
         * Resident permanent address, in parts.
         *
         * Previously the address lived only as a comma-joined string that
         * every form split back apart by position. Any address with a
         * different number of commas landed in the wrong boxes, and the
         * "request a change" screen compared reassembled strings, so it
         * reported no change when there was one. The parts are now the
         * source of truth; `address` is the derived display line.
         */
        ['residents', 'household_number', "ALTER TABLE residents ADD COLUMN household_number varchar(50) DEFAULT NULL"],
        ['residents', 'purok',            "ALTER TABLE residents ADD COLUMN purok varchar(100) DEFAULT NULL"],
        ['residents', 'street_highway',   "ALTER TABLE residents ADD COLUMN street_highway varchar(150) DEFAULT NULL"],
        ['residents', 'barangay',         "ALTER TABLE residents ADD COLUMN barangay varchar(100) DEFAULT NULL"],
        ['residents', 'municipality',     "ALTER TABLE residents ADD COLUMN municipality varchar(100) DEFAULT NULL"],
        ['residents', 'postal_code',      "ALTER TABLE residents ADD COLUMN postal_code varchar(10) DEFAULT NULL"],
        ['residents', 'province',         "ALTER TABLE residents ADD COLUMN province varchar(100) DEFAULT NULL"],
        ['residents', 'courtesy_title',   "ALTER TABLE residents ADD COLUMN courtesy_title varchar(20) DEFAULT NULL AFTER resident_number"],

        /**
         * Temporary residence.
         *
         * Only meaningful when residency_status is 'temporary': a resident
         * staying in a boarding house still has a permanent address
         * elsewhere, and the health center needs both.
         */
        ['residents', 'residence_type',        "ALTER TABLE residents ADD COLUMN residence_type varchar(5) DEFAULT NULL"],
        ['residents', 'temporary_address',     "ALTER TABLE residents ADD COLUMN temporary_address varchar(255) DEFAULT NULL"],
        ['residents', 'temp_household_number', "ALTER TABLE residents ADD COLUMN temp_household_number varchar(50) DEFAULT NULL"],
        ['residents', 'temp_purok',            "ALTER TABLE residents ADD COLUMN temp_purok varchar(100) DEFAULT NULL"],
        ['residents', 'temp_street_highway',   "ALTER TABLE residents ADD COLUMN temp_street_highway varchar(150) DEFAULT NULL"],
        ['residents', 'temp_barangay',         "ALTER TABLE residents ADD COLUMN temp_barangay varchar(100) DEFAULT NULL"],
        ['residents', 'temp_municipality',     "ALTER TABLE residents ADD COLUMN temp_municipality varchar(100) DEFAULT NULL"],
        ['residents', 'temp_postal_code',      "ALTER TABLE residents ADD COLUMN temp_postal_code varchar(10) DEFAULT NULL"],
        ['residents', 'temp_province',         "ALTER TABLE residents ADD COLUMN temp_province varchar(100) DEFAULT NULL"],

        // Change requests carry the same seven parts the resident edited.
        // Temporary residents can now request a correction to the address
        // they are actually staying at, not just their permanent one.
        ['resident_update_requests', 'temporary_address',     "ALTER TABLE resident_update_requests ADD COLUMN temporary_address varchar(255) DEFAULT NULL"],
        ['resident_update_requests', 'temp_household_number', "ALTER TABLE resident_update_requests ADD COLUMN temp_household_number varchar(50) DEFAULT NULL"],
        ['resident_update_requests', 'temp_purok',            "ALTER TABLE resident_update_requests ADD COLUMN temp_purok varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'temp_street_highway',   "ALTER TABLE resident_update_requests ADD COLUMN temp_street_highway varchar(150) DEFAULT NULL"],
        ['resident_update_requests', 'temp_barangay',         "ALTER TABLE resident_update_requests ADD COLUMN temp_barangay varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'temp_municipality',     "ALTER TABLE resident_update_requests ADD COLUMN temp_municipality varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'temp_postal_code',      "ALTER TABLE resident_update_requests ADD COLUMN temp_postal_code varchar(10) DEFAULT NULL"],
        ['resident_update_requests', 'temp_province',         "ALTER TABLE resident_update_requests ADD COLUMN temp_province varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'purok',          "ALTER TABLE resident_update_requests ADD COLUMN purok varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'street_highway', "ALTER TABLE resident_update_requests ADD COLUMN street_highway varchar(150) DEFAULT NULL"],
        ['resident_update_requests', 'barangay',       "ALTER TABLE resident_update_requests ADD COLUMN barangay varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'postal_code',    "ALTER TABLE resident_update_requests ADD COLUMN postal_code varchar(10) DEFAULT NULL"],
        ['resident_update_requests', 'previous_address', "ALTER TABLE resident_update_requests ADD COLUMN previous_address varchar(255) DEFAULT NULL"],

        // A resident's identity fields are reviewed, not self-served, so the
        // request row has to be able to carry each of them.
        ['resident_update_requests', 'courtesy_title', "ALTER TABLE resident_update_requests ADD COLUMN courtesy_title varchar(20) DEFAULT NULL"],
        ['resident_update_requests', 'middle_name',    "ALTER TABLE resident_update_requests ADD COLUMN middle_name varchar(50) DEFAULT NULL"],
        ['resident_update_requests', 'name_extension', "ALTER TABLE resident_update_requests ADD COLUMN name_extension varchar(20) DEFAULT NULL"],
        ['resident_update_requests', 'birthdate',      "ALTER TABLE resident_update_requests ADD COLUMN birthdate date DEFAULT NULL"],

        ['resident_update_requests', 'first_name', "ALTER TABLE resident_update_requests ADD COLUMN first_name varchar(50) DEFAULT NULL"],
        ['resident_update_requests', 'last_name', "ALTER TABLE resident_update_requests ADD COLUMN last_name varchar(50) DEFAULT NULL"],
        ['resident_update_requests', 'household_number', "ALTER TABLE resident_update_requests ADD COLUMN household_number varchar(50) DEFAULT NULL"],
        ['resident_update_requests', 'barangay_subdivision', "ALTER TABLE resident_update_requests ADD COLUMN barangay_subdivision varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'municipality', "ALTER TABLE resident_update_requests ADD COLUMN municipality varchar(100) DEFAULT NULL"],
        ['resident_update_requests', 'province', "ALTER TABLE resident_update_requests ADD COLUMN province varchar(100) DEFAULT NULL"],

        /**
         * Archive (soft delete) for resident and staff accounts.
         *
         * Deleting a resident used to run DELETE FROM residents, which the
         * foreign key on `responses` cascaded into — the account and every
         * survey answer it had ever submitted went in one click, with no way
         * back. Deletion now only stamps these columns. The row stays put,
         * the responses stay attached to it, and an administrator can put
         * the account back from Deleted Accounts.
         *
         * deleted_at IS NULL is the test for "this account is live". Every
         * listing, count and the login check filter on it.
         */
        ['residents', 'deleted_at',       "ALTER TABLE residents ADD COLUMN deleted_at datetime DEFAULT NULL"],
        ['residents', 'deleted_by',       "ALTER TABLE residents ADD COLUMN deleted_by int(11) DEFAULT NULL"],
        ['residents', 'deleted_by_name',  "ALTER TABLE residents ADD COLUMN deleted_by_name varchar(100) DEFAULT NULL"],
        ['residents', 'delete_reason',    "ALTER TABLE residents ADD COLUMN delete_reason varchar(255) DEFAULT NULL"],
        ['staff',     'deleted_at',       "ALTER TABLE staff ADD COLUMN deleted_at datetime DEFAULT NULL"],
        ['staff',     'deleted_by',       "ALTER TABLE staff ADD COLUMN deleted_by int(11) DEFAULT NULL"],
        ['staff',     'deleted_by_name',  "ALTER TABLE staff ADD COLUMN deleted_by_name varchar(100) DEFAULT NULL"],
        ['staff',     'delete_reason',    "ALTER TABLE staff ADD COLUMN delete_reason varchar(255) DEFAULT NULL"],

        /* Courtesy title and gender on each repeatable family row.
           These belong in this second pass, not the first: resident_children
           and resident_character_references are created further up this file
           but *after* the first migration list runs, so an ALTER placed there
           would find no table on a fresh database and be skipped. */
        ['resident_children',             'courtesy_title', "ALTER TABLE resident_children ADD COLUMN courtesy_title varchar(20) DEFAULT NULL AFTER resident_id"],
        ['resident_children',             'gender',         "ALTER TABLE resident_children ADD COLUMN gender varchar(20) DEFAULT NULL"],
        ['resident_character_references', 'courtesy_title', "ALTER TABLE resident_character_references ADD COLUMN courtesy_title varchar(20) DEFAULT NULL AFTER resident_id"],
        ['resident_character_references', 'gender',         "ALTER TABLE resident_character_references ADD COLUMN gender varchar(20) DEFAULT NULL"],
    ]);

    foreach ($auto_columns as $column) {
        list($table, $name, $sql) = $column;
        try {
            $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$name'");
            if ($check && $check->num_rows === 0) {
                $conn->query($sql);
            }
        } catch (mysqli_sql_exception $e) {
            // Log and carry on: a single column that cannot be added must
            // not take the whole site down with a fatal error page.
            error_log("Schema check failed for $table.$name: " . $e->getMessage());
        }
    }

    /**
     * Backfill resident address parts from the legacy single-line address.
     *
     * The old form always wrote exactly four parts in a known order, so a
     * four-part string maps cleanly. Anything else is left for a human to
     * re-enter rather than guessed at — a wrong guess would put a barangay
     * in the province box and look correct until someone read it.
     */
    $legacy_addresses = $conn->query("SELECT resident_id, address FROM residents
                                       WHERE (household_number IS NULL OR household_number = '')
                                         AND address IS NOT NULL AND address <> ''");
    if ($legacy_addresses && $legacy_addresses->num_rows > 0) {
        $split = $conn->prepare("UPDATE residents
                                    SET household_number = ?, barangay = ?, municipality = ?, province = ?
                                  WHERE resident_id = ?");
        while ($row = $legacy_addresses->fetch_assoc()) {
            $parts = preg_split('/\s*,\s*/', trim($row['address']));
            if (count($parts) !== 4) {
                continue;
            }
            $split->bind_param('ssssi', $parts[0], $parts[1], $parts[2], $parts[3], $row['resident_id']);
            $split->execute();
        }
        $split->close();
    }

    // Carry any single-line emergency contact into the name half rather than
    // dropping it; the number can be re-entered.
    $conn->query("UPDATE staff SET emergency_contact_name = emergency_contact
                   WHERE (emergency_contact_name IS NULL OR emergency_contact_name = '')
                     AND emergency_contact IS NOT NULL AND emergency_contact <> ''");

    /**
     * Widen the gender columns before migrating their values.
     *
     * residents.gender was an ENUM('male','female') and staff.gender a
     * varchar(10). Both reject the current option set — an ENUM truncates
     * anything outside its list, and 'prefer_not_to_say' is 17 characters.
     * Run before the UPDATEs below or every write is silently truncated.
     */
    $resident_gender = $conn->query("SHOW COLUMNS FROM residents LIKE 'gender'");
    if ($resident_gender && ($col = $resident_gender->fetch_assoc())) {
        if (stripos($col['Type'], 'varchar(20)') === false) {
            $conn->query("ALTER TABLE residents MODIFY COLUMN gender varchar(20) DEFAULT NULL");
        }
    }
    $staff_gender = $conn->query("SHOW COLUMNS FROM staff LIKE 'gender'");
    if ($staff_gender && ($col = $staff_gender->fetch_assoc())) {
        if (stripos($col['Type'], 'varchar(20)') === false) {
            $conn->query("ALTER TABLE staff MODIFY COLUMN gender varchar(20) DEFAULT NULL");
        }
    }

    /**
     * Migrate the old two-value gender column to the current option set.
     *
     * Runs every load but matches nothing once done, so it is safe to leave
     * in place rather than tracking whether it has already been applied.
     */
    $conn->query("UPDATE residents SET gender = 'man'   WHERE gender = 'male'");
    $conn->query("UPDATE residents SET gender = 'woman' WHERE gender = 'female'");
    $conn->query("UPDATE staff     SET gender = 'man'   WHERE gender = 'male'");
    $conn->query("UPDATE staff     SET gender = 'woman' WHERE gender = 'female'");
}

/**
 * Stop here, readably, when there is no connection.
 *
 * Every page that includes this file goes straight on to $conn->prepare().
 * Letting that happen with $conn = null turns a temporary database outage
 * into a fatal error naming our file paths and line numbers. Ending the
 * request with a plain explanation is both safer and more useful to whoever
 * is looking at the screen.
 */
if ($conn === null) {
    http_response_code(503);
    header('Retry-After: 60');
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Service unavailable</title>
        <style>
            body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
                   font-family:"Segoe UI",system-ui,sans-serif; background:#f2f6f4; color:#26332e; padding:24px; }
            .panel { background:#fff; border:1px solid #dbe5e0; border-radius:12px; padding:32px;
                     max-width:440px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
            h1 { margin:0 0 12px; font-size:1.25rem; }
            p { margin:0 0 10px; line-height:1.55; color:#54635d; }
        </style>
    </head>
    <body>
        <div class="panel">
            <h1>The system is temporarily unavailable</h1>
            <p>We could not reach the records database just now. Nothing you have submitted has been lost.</p>
            <p>Please try again in a few minutes. If this keeps happening, let the Barangay Health Center staff know.</p>
        </div>
    </body>
    </html><?php
    exit;
}
?>
