<?php
namespace Statssolver;

use mysqli;
use \Stripe\Customer as Customer;
use \Stripe\Stripe as Stripe;
use \Stripe\Subscription as Subscription;

/***
 * Handles functions
 */
class Statssolver {
    /***
     * Useful variables
     */
    public $stripe;
    public $phpmailer;
    public $smtp;
    public $exception;
    public $subscription;
    public $customer;
    public $dbc = [
        'host'   => 'localhost',
        'dbname' => 'statsso1_solver',
        'dbuser' => 'statsso1_solver',
        'dbpass' => 'Pepperoni66!!',
    ];
    public $db;

    const email       = 'admin@statssolver.com';
    const domain      = 'https://statssolver.com';
    const title       = 'Stats Solver';
    const mail_header = "MIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1\r\nFrom: 'Stats Solver' <admin@statssolver.com>";

    const products = [
        "pids" => ["1", "2"],
        "1"    => "price_1IF2OWLZeLIbxXREpZ6TD7mg",
        "2"    => "price_1IF2OWLZeLIbxXREpZ6TD7mg",
    ];

    const api_key = [
        "publishable_key" => "pk_test_51Hwkp2LZeLIbxXRE9nJifBOH9Fa8Tg72QVW0hao2yghMY78kWW8uSVDNYbkQe1Oh9hG8uXKPkMGtlnkbFdqiY6SS00ql7gwnjX",
        "secret_key"      => "sk_test_51Hwkp2LZeLIbxXREtmLlCcrFxt6qlskV9x1vjZHNr1mzDFxLB2AO0esF6dAspnvgfozOBKlaZI2EeFj2RBPbBQIs007bhmu8po",
    ];

    const plan = [
        '1' => 'Basic',
        '2' => 'Premium',
    ];

    /***
     * Builds the class
     */
    function __construct() {
        $this->stripe = new Stripe();
        $this->init_stripe();

        $this->customer     = new Customer();
        $this->subscription = new Subscription();

        $this->db = new mysqli(
            $this->dbc['host'],
            $this->dbc['dbuser'],
            $this->dbc['dbpass'],
            $this->dbc['dbname']
        );

        $this->define_constants();

        if ( session_status() == PHP_SESSION_NONE ) {
        }
        session_start();
    }

    /**
     * Defines important constants
     *
     * @return void
     */
    public function define_constants() {
        // Constants
        define( 'TITLE', self::title );
        define( 'DOMAIN', self::domain );
        define( 'EMAIL', self::email );
        // Globals
        $GLOBALS['TITLE']  = TITLE;
        $GLOBALS['DOMAIN'] = DOMAIN;
        $GLOBALS['EMAIL']  = EMAIL;
    }

    /**
     * Initializes stripe functions
     *
     * @return void
     */
    public function init_stripe() {
        $this->stripe::setApiKey( self::api_key['secret_key'] );
    }

    /**
     * Sends an email
     *
     * @param  array  $tt
     * @return void
     */
    public function send_mail( $atts = [] ) {
        return mail(
            $atts['to'],
            $atts['subject'],
            $atts['body'],
            self::mail_header,
            "-f " . self::email
        );
    }

    /**
     * Creates a new user
     *
     * @return void
     */
    public function create_user( $atts ) {
        $password = $this->create_password();
        $ui       = [
            'email'    => $atts['email'],
            'plan'     => $atts['plan'],
            'plan_id'  => $atts['plan_id'],
            'hash'     => $password['hash'],
            'password' => $password['password'],
            'regdate'  => time(),
        ];

        $user = $this->db->prepare( "INSERT INTO users (email, plan, plan_id, password, regdate) VALUES(?, ?, ?, ?, ?)" );
        $user->bind_param( 'sssss', $ui['email'], $ui['plan'], $ui['plan_id'], $ui['hash'], $ui['regdate'] );
        $user->execute();

        return $ui;
    }

    /**
     * Sends an email on user registration
     *
     * @return void
     */
    public function send_user_registration_email() {

    }

    /**
     * Logins an user
     *
     * @param  [type] $atts
     * @return void
     */
    public function login() {
        $email    = isset( $_POST['email'] ) ? $_POST['email'] : '';
        $password = isset( $_POST['password'] ) ? $_POST['password'] : '';

        if ( empty( $email ) || empty( $password ) ) {
            $this->login_page( 'failed=true&message=Unable to login, please try again.' );
        }

        $user = $this->db->query(
            "SELECT email, password, plan, plan_id FROM users WHERE email='{$email}'"
        );

        if ( $user->num_rows > 0 ) {
            $user = $user->fetch_assoc();

            $password_verified = password_verify( $password, $user['password'] );

            if ( $email == $user['email'] && $password_verified ) {
                // Login succeeded
                $_SESSION['logged']           = true;
                $_SESSION['user']             = $user;
                $_SESSION['user']['password'] = $password;

                $this->home_page();
            } else {
                $this->login_failed( "Invalid credentials, try again!" );
            }
        } else {
            $this->login_failed( "User not found, please check your credentials and try again." );
        }
    }

    /**
     * Return a failed login template
     *
     * @param  string $msg
     * @return void
     */
    public function login_failed( $msg = 'Failed to login' ) {
        $this->login_page( "failed=true&message={$msg}" );
    }

    /**
     * Logs out an user
     *
     * @param  [type] $user_id
     * @return void
     */
    public function logout() {
        unset( $_SESSION['logged'], $_SESSION['user'] );
        $this->home_page();
    }

    /**
     * Checks if an email have registerd already
     *
     * @param  [type] $email
     * @return void
     */
    public function have_acc( $email ) {
        $have = $this->db->query(
            "SELECT id FROM users WHERE email='{$email}'"
        );

        return $have->num_rows > 0 ? true : false;
    }

    /**
     * Checks if the user logged in
     *
     * @return boolean
     */
    public function islogged() {
        if ( isset( $_SESSION['logged'] ) && $_SESSION['logged'] ) {
            $user = $this->db->query(
                "SELECT email, password FROM users WHERE email='{$_SESSION['user']['email']}'"
            );

            if ( $user != false && $user->num_rows > 0 ) {
                $user = $user->fetch_assoc();

                $password_verified = password_verify( $_SESSION['user']['password'], $user['password'] );
                if ( $_SESSION['user']['email'] == $user['email'] && $password_verified ) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Activates and creates a customer in stripe
     *
     * @return void
     */
    public function activate_package( $atts ) {

        $customer = $this->customer::create( [
            'email'  => $atts['email'],
            'source' => $atts['token'],
        ] );

        $this->subscription::create( [
            "customer" => $customer->id,
            "items"    => [
                [
                    "plan" => self::products[$atts['pid']],
                ],
            ],
        ] );
    }

    /**
     * Updates current plan of an user
     *
     * @param  [type] $email
     * @param  [type] $plan
     * @return void
     */
    public function update_user_plan( $email, $plan ) {
        $user = $this->db->prepare( "UPDATE users SET plan='{$plan}' WHERE email='{$email}'" );
        $user->bind_param( 's', $plan );
        $user->execute();
    }

    /**
     * Creates a password
     *
     * @return void
     */
    public function create_password() {
        $password = "qwertzuioplkjhgfdsayxcvbnm1234567890";
        $password = str_shuffle( $password );
        $password = strtoupper( substr( $password, 0, 10 ) );
        $hash     = password_hash( $password, PASSWORD_BCRYPT );

        return [
            'password' => $password,
            'hash'     => $hash,
        ];
    }

    /**
     * Handles payments requeset
     *
     * @return void
     */
    public function handle_payment() {

        // Checks for parameters validation
        if ( ! isset( $_GET['pid'] ) || ! in_array( $_GET['pid'], self::products['pids'] ) || ! isset( $_POST['stripeToken'] ) || ! isset( $_POST['stripeEmail'] ) ) {
            header( 'Location: index.php' );
            exit();
        }

        // Collects parameters
        $pid   = isset( $_GET['pid'] ) ? $_GET['pid'] : 0;
        $token = isset( $_POST['stripeToken'] ) ? $_POST['stripeToken'] : '';
        $email = isset( $_POST['stripeEmail'] ) ? $_POST['stripeEmail'] : '';
        $info  = [
            'pid'   => $pid,
            'token' => $token,
            'email' => $email,
        ];
        $info['plan']    = self::plan[$info['pid']];
        $info['plan_id'] = self::products[$info['pid']];

        // Activates the package
        $this->activate_package( $info );

        $new = false;
        // User creation part

        if ( $this->have_acc( $info['email'] ) ) {
            $this->update_user_plan( $info['email'], $info['pid'] );
        } else {

            $user = $this->create_user( $info );
            $new  = true;
        }

        // Email user details
        if ( $new ) {
            $sent = $this->send_mail(
                [
                    'to'      => $info['email'],
                    'subject' => "Your account created successfully",
                    'body'    => $this->email_template(
                        [
                            'email'    => $info['email'],
                            'password' => $user['password'],
                            'plan'     => self::plan[$info['pid']],
                        ]
                    ),
                ]
            );

            if ( ! $sent ) {
                $this->welcome_page(
                    "status=failedemail&password={$user['password']}&email={$user['email']}"
                );
            } else {
                $this->welcome_page(
                    "status=olduser"
                );
            }
        } else {
            $this->update_user_plan( $info['email'], $info['pid'] );
            $this->welcome_page(
                'status=olduser'
            );
        }

    }

    /**
     * Creates a template for user registration information
     *
     * @param  array  $atts
     * @return void
     */
    public function email_template( $atts = [] ) {
        return "
        Hey,
        <br><br>
        Thank you for the purchase {$this->title} {$atts['plan']}. Your login details are included below:<br><br>
        <b>Username</b>: {$atts['email']}<br>
        <b>Password</b>: {$atts['password']}<br><br>

        <a href='https://{$this->domain}/login.php'>Click Here To Login</a><br><br>

        Thanks,<br>
        {$this->title} Team.
        ";
    }

    /**
     * Returns a template
     *
     * @param  [type] $path
     * @return void
     */
    public function template( $path ) {
        ob_start();
        include $path;
        return ob_get_clean();
    }

    /**
     * Navigates to home
     *
     * @return void
     */
    public function home_page( $params = '' ) {
        $this->navigate( 'index.php', $params );
    }

    /**
     * Navigates to login page
     *
     * @param  string $params
     * @return void
     */
    public function login_page( $params = '' ) {
        $this->navigate( 'login.php', $params );
    }

    /**
     * Navigate to welcome page
     *
     * @param  string $params
     * @return void
     */
    public function welcome_page( $params = '' ) {
        $this->navigate( 'welcome.php', $params );
    }

    /**
     * Navigates to a page
     *
     * @param  string $page
     * @param  string $params
     * @return void
     */
    public function navigate( $page = '', $params = '' ) {
        if ( ! empty( $params ) ) {
            $params = "?{$params}";
        }
        header( "Location: {$page}{$params}" );
        exit();
    }

    /**
     * Checks if the current user have the basic package
     *
     * @return void
     */
    public function current_user_have_basic_package() {
        if ( isset( $_SESSION['user'] ) && $_SESSION['user']['plan'] == 'Basic' ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the current user have the preimum package
     *
     * @return void
     */
    public function current_user_have_premium_package() {
        if ( isset( $_SESSION['user'] ) && $_SESSION['user']['plan'] == 'Basic' ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if an user have a specific plan
     *
     * @param  string $plan
     * @return void
     */
    public function user_have_plan( $plan = '' ) {
        if ( isset( $_SESSION['user'] ) && $_SESSION['user']['plan'] == $plan ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Restricts login and join page for logged in users
     *
     * @return void
     */
    public function check_for_restrication() {     
        if ( $this->islogged() ) {
            header("Location: index.php");
        }
    }

}