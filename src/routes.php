<?php
/** @var Type $this */
/** @var \Slim\App $app */
/** @var KillerSnailsAccounts\ksaPDO $this ->oauth_storage */

// Psr-7 Request and Response interfaces
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use KillerSnailsAccounts\ksaPDO;

/* ---- OAuth Responses ---- */
$app->get('/authorize', function (Request $request, Response $response) {
    return $this->renderer->render($response, 'authorize.phtml', []);
});

$app->post('/authorize', function (Request $request, Response $response) {
    $oauth_request = OAuth2\Request::createFromGlobals();
    $oauth_response = new OAuth2\Response();

    // validate the authorize request
    if (!$this->oauth_server->validateAuthorizeRequest($oauth_request, $oauth_response)) {
        $oauth_response->send();
        die;
    }

    // print the authorization code if the user has authorized your client
    $is_authorized = ($_POST['authorized'] === 'yes');
    $this->oauth_server->handleAuthorizeRequest($oauth_request, $oauth_response, $is_authorized);
    if ($is_authorized) {
        // this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
        $code = substr($ouath_response->getHttpHeader('Location'), strpos($oauth_response->getHttpHeader('Location'), 'code=') + 5, 40);
        exit("SUCCESS! Authorization Code: $code");
    }
    $oauth_response->send();
});

$app->map(["GET", "POST"], '/token', function (Request $request, Response $response) {
    $this->oauth_server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
});

$app->map(["GET", "POST"], '/tokeninfo', function (Request $request, Response $response) {
    // Handle a request to a resource and authenticate the access token
    if (!$this->oauth_server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
        die($this->oauth_server->getResponse()->send());
    }

    $token = $this->oauth_server->getAccessTokenData(OAuth2\Request::createFromGlobals());
    $expires_in = $token["expires"] - time();
    $tokeninfo = $token;
    unset($tokeninfo["expires"]);
    $tokeninfo["expires_in"] = $expires_in;
    echo json_encode($tokeninfo);
});

/* ---- Registartion of Users ---- */
$app->get('/register', function (Request $request, Response $response) {
    return $this->renderer->render($response, 'registration/new.phtml', []);
});

$app->post('/register', function (Request $request, Response $response) {
    if (
        (isset($_POST["firstname"]) && trim($_POST["firstname"]) != "") &&
        (isset($_POST["lastname"]) && trim($_POST["lastname"]) != "") &&
        (isset($_POST["email"]) && trim($_POST["email"]) != "") &&
        (isset($_POST["password"]) && trim($_POST["password"]) != "") &&
        (isset($_POST["confirm-password"]) && trim($_POST["confirm-password"]) != "") &&
        ($_POST["password"] == $_POST["confirm-password"])
    ) {
        // Check if email is already registered, if so, return error only.
        if ($this->oauth_storage->getUser($_POST["email"])) {
            return $this->renderer->render($response, 'registration/new.phtml', [
                "message" => "This email already exists.",
                "firstname" => $_POST["firstname"],
                "lastname" => $_POST["lastname"],
                "email" => $_POST["email"],
            ]);
        } else {
            $this->oauth_storage->setUser($_POST["email"], $_POST["password"], $_POST["firstname"], $_POST["lastname"]);
            // Clear data, so we force a new login when verifying
            unsetAllSessionInfo($this->cookie_domain);
            return $this->renderer->render($response, 'registration/success.phtml', [
                "email" => $_POST["email"],
                "fullname" => $_POST["firstname"] . " " . $_POST["lastname"]
            ]);
        }
    } else {
        return $this->renderer->render($response, 'registration/new.phtml', [
            "message" => "Please fill-in all fields. Both passwords must match!",
            "firstname" => $_POST["firstname"],
            "lastname" => $_POST["lastname"],
            "email" => $_POST["email"],
        ]);
       // return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
    }
});

/* ---- API which require OAuth ---- */
$app->get('/profile', function (Request $request, Response $response) {
    $accessToken = $request->getAttribute("accessToken");
    echo json_encode(array('success' => true, 'message' => $this->oauth_storage->getProfile($accessToken["user_id"])));
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/courses/{user_id}', function (Request $request, Response $response, $args) {
    $accessToken = $request->getAttribute("accessToken");
    echo json_encode(array('success' => true, 'message' => $this->oauth_storage->getCourses($args["user_id"])));
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/students/{course_id}', function (Request $request, Response $response, $args) {
    $accessToken = $request->getAttribute("accessToken");
    echo json_encode(array('success' => true, 'message' => $this->oauth_storage->getStudentsEnrolledInCourse($args["course_id"])));
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/teachers/{course_id}', function (Request $request, Response $response, $args) {
    $accessToken = $request->getAttribute("accessToken");
    echo json_encode(array('success' => true, 'message' => $this->oauth_storage->getTeacherForCourse($args["course_id"])));
})->add(new VerifyOrRenewToken($app->getContainer()));

/* ---- Web Pages for Accounts ---- */
$app->get('/update_profile', function (Request $request, Response $response) {
    return $this->renderer->render($response, 'profile/update.phtml', ["profile" => $request->getAttribute("profile")]);
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->post('/update_profile', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $accessToken = $request->getAttribute("accessToken");
    $this->oauth_storage->updateProfile($accessToken["user_id"], $input["firstname"], $input["lastname"], $input["avatar"], $input["schoolname"]);
    return $response->withRedirect("./");
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/show_course/{course_id}', function (Request $request, Response $response, $args) {
    return $this->renderer->render($response, 'course/show.phtml', [
        "profile" => $request->getAttribute("profile"),
        "course" => $this->oauth_storage->getCourse($args["course_id"]),
        "students" => $this->oauth_storage->getStudentsEnrolledInCourse($args["course_id"])
    ]);
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/create_course', function (Request $request, Response $response) {
    return $this->renderer->render($response, 'course/create.phtml', ["profile" => $request->getAttribute("profile")]);
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->post('/create_course', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $accessToken = $request->getAttribute("accessToken");
    $courseId = $this->oauth_storage->setCourse($accessToken["user_id"], $input["class_name"]);
    $course = $this->oauth_storage->getCourse($courseId);
    $teacher = $this->oauth_storage->getProfile($course["owner_id"]);
    if ($courseId !== false) {
        $count = 0;
        do {
            if ($input["student_email" . $count] != "") {
                $this->oauth_storage->addStudentToCourse($courseId, $input["student_email" . $count], $input["student_firstname" . $count], $input["student_lastname" . $count], $course["title"], $teacher["last_name"]);
                $count++;
            } else {
                $count = -1;
            }
        } while ($count > -1);
    }
    return $response->withRedirect("./");
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/enrolled/{code}', function (Request $request, Response $response, $args) {
    $code = $args["code"];
    $decoded = explode(":", base64_decode($code));
    $verify_code = md5(ksaPDO::base_convert($decoded[0], 36, 10));
    $username = ksaPDO::base_convert($decoded[1], 36, 10);

    $userProfile = $this->oauth_storage->getProfile($username);
    if ($this->oauth_storage->isUserVerified($username)) {
        return $response->withRedirect("../");
    } else {
        if ($this->oauth_storage->verifyEmail($username, $verify_code, false)) {
            return $this->renderer->render($response, "password_reset/first_time.phtml", [
                "code" => $code,
                "email" => $userProfile["email"],
                "firstname" => $userProfile["first_name"],
                "lastname" => $userProfile["last_name"],
            ]);
        }
    }
    return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
});

$app->post('/create_password', function (Request $request, Response $response, $args) {
    if (
        (isset($_POST["firstname"]) && trim($_POST["firstname"]) != "") &&
        (isset($_POST["lastname"]) && trim($_POST["lastname"]) != "") &&
        (isset($_POST["email"]) && trim($_POST["email"]) != "") &&
        (isset($_POST["password"]) && trim($_POST["password"]) != "") &&
        (isset($_POST["confirm-password"]) && trim($_POST["confirm-password"]) != "") &&
        ($_POST["password"] == $_POST["confirm-password"])
    ) {
        $this->oauth_storage->setUser($_POST["email"], $_POST["password"], $_POST["firstname"], $_POST["lastname"]);

        $code = $_POST["code"];
        $decoded = explode(":", base64_decode($code));
        $verify_code = md5(ksaPDO::base_convert($decoded[0], 36, 10));
        $username = ksaPDO::base_convert($decoded[1], 36, 10);
        $this->oauth_storage->verifyEmail($username, $verify_code);

        unsetAllSessionInfo($this->cookie_domain);
        return $this->renderer->render($response, 'password_reset/success.phtml', [
            "email" => $_POST["email"],
            "fullname" => $_POST["firstname"] . " " . $_POST["lastname"]
        ]);
    } else {
        if (isset($_POST["code"]) && trim($_POST["code"])) {
            return $response->withRedirect("./enrolled/" . $_POST["code"] . "?message=Password%20not%20updated");
        }
    }
    return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
});


/* ---- Verification Pages ---- */
$app->get('/verify/{code}', function (Request $request, Response $response, $args) {
    $code = $args["code"];
    $decoded = explode(":", base64_decode($code));
    $verify_code = md5(ksaPDO::base_convert($decoded[0], 36, 10));
    $username = ksaPDO::base_convert($decoded[1], 36, 10);

    if ($this->oauth_storage->isUserVerified($username)) {
        return $response->withRedirect("../");
    } else {
        if ($this->oauth_storage->verifyEmail($username, $verify_code)) {
            return $this->renderer->render($response, "verification/success.phtml");
        }
    }
    return $this->renderer->render($response, "verification/failed.phtml");
});

$app->get('/need_to_verify', function (Request $request, Response $response, $args) {
    return $this->renderer->render($response, "verification/need_to.phtml");
});

$app->post('/resend_verification', function (Request $request, Response $response, $args) {
    $input = $request->getParsedBody();
    $email = $input["email"];

    if ($this->oauth_storage->resendVerification($email) === true) {
        return $this->renderer->render($response, "verification/resent.phtml", ["email" => $email]);
    }
    return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
});

/* ---- Password ---- */
$app->get('/forgot_password', function (Request $request, Response $response, $args) {
    return $this->renderer->render($response, "login/forgot.phtml");
});

$app->post('/forgot_password', function (Request $request, Response $response, $args) {
    $input = $request->getParsedBody();
    $email = $input["email"];

    if ($this->oauth_storage->forgotPassword($email) === true) {
        return $this->renderer->render($response, "password_reset/sent.phtml", [
            "email" => $email
        ]);
    } else {
        return $this->renderer->render($response, "login/forgot.phtml", [
            "message" => "This email doesn't exists.",
            "email" => $email
        ]);
//        return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
    }
});

$app->get('/reset_password/{code}', function (Request $request, Response $response, $args) {
    $code = $args["code"];
    $decoded = explode(":", base64_decode($code));
    $reset_code = md5(ksaPDO::base_convert($decoded[0], 36, 10));
    $username = ksaPDO::base_convert($decoded[1], 36, 10);

    $userProfile = $this->oauth_storage->verifyPasswordReset($username, $reset_code);
    if ($userProfile !== false) {
        $profile = $this->oauth_storage->getProfile($username);
        return $this->renderer->render($response, "password_reset/reset.phtml", [
            "code" => $code,
            "email" => $userProfile["email"],
            "firstname" => $profile["first_name"],
            "lastname" => $profile["last_name"],
            "message" => $_REQUEST["message"]
        ]);
    }
    return $this->renderer->render($response, "password_reset/invalid.phtml");
});

$app->post('/reset_password', function (Request $request, Response $response, $args) {
    if (
        (isset($_POST["firstname"]) && trim($_POST["firstname"]) != "") &&
        (isset($_POST["lastname"]) && trim($_POST["lastname"]) != "") &&
        (isset($_POST["email"]) && trim($_POST["email"]) != "") &&
        (isset($_POST["password"]) && trim($_POST["password"]) != "") &&
        (isset($_POST["confirm-password"]) && trim($_POST["confirm-password"]) != "") &&
        ($_POST["password"] == $_POST["confirm-password"])
    ) {
        $this->oauth_storage->setUser($_POST["email"], $_POST["password"], $_POST["firstname"], $_POST["lastname"]);
        unsetAllSessionInfo($this->cookie_domain);
        return $this->renderer->render($response, 'password_reset/success.phtml', [
            "email" => $_POST["email"],
            "fullname" => $_POST["firstname"] . " " . $_POST["lastname"]
        ]);
    } else {
        if (isset($_POST["code"]) && trim($_POST["code"])) {
            return $response->withRedirect("./reset_password/" . $_POST["code"] . "?message=Password%20not%20updated");
        }
    }
    return $this->renderer->render($response, "handlers/unhandled.phtml", ["request" => $request]);
});

/* ---- Generic Pages ---- */
$app->get('/', function (Request $request, Response $response) {
    return $this->renderer->render($response, 'index.phtml', [
        "storage" => $this->oauth_storage,
        /* @NOTE Just  for testing... */
        "accessToken" => $request->getAttribute("accessToken")
    ]);
})->add(new VerifyOrRenewToken($app->getContainer()));

$app->get('/login', function (Request $request, Response $response) {
    if (isset($_COOKIE["ksa_at"]) && isset($_COOKIE["ksa_rt"])) {
        return $response->withRedirect("./");
    }
    return $this->renderer->render($response, 'login/login.phtml');
});

$app->post('/login', function (Request $request, Response $response) {
    if ((isset($_POST["username"]) && trim($_POST["username"]) != "") && (isset($_POST["password"]) && trim($_POST["password"]) != "")) {
        if ($this->oauth_storage->checkUserCredentials($_POST["username"], $_POST["password"])) {
            $oauth_request = KillerSnailsAccounts\ksaRequest::createFromGlobalsWithGrantType($this->client_id, $this->client_secret);
            $results = $this->oauth_server->handleTokenRequest($oauth_request);
            $_SESSION["ksa_token"] = $results->getParameters();
            setCookiesForLogin($this->cookie_domain, $this->client_id, $this->client_secret);
            return $response->withRedirect("./");
        } else {
            return $this->renderer->render($response, 'login/login.phtml', [
                "message" => "Invalid username/password. Please try again!",
                "username" => $_POST["username"],
                "password" => $_POST["password"]
            ]);
        }
    }
    else {
        return $this->renderer->render($response, 'login/login.phtml', [
            "message" => "Invalid username/password. Please try again!",
            "username" => $_POST["username"],
            "password" => $_POST["password"]
        ]);
    }
});

$app->get('/logout', function (Request $request, Response $response) {
    unsetAllSessionInfo($this->cookie_domain);
    return $response->withRedirect("./");
});

/* ---- Misc Functions used from server ---- */
function unsetAllSessionInfo($cookieDomain)
{
    unset($_SESSION['ksa_token']);

    setcookie("ksa_at", "", 1, "/", $cookieDomain);
    setcookie("ksa_rt", "", 1, "/", $cookieDomain);
    setcookie("ksa_s", "", 1, "/", $cookieDomain);
    setcookie("ksa_tt", "", 1, "/", $cookieDomain);
    setcookie("ksa_cid", "", 1, "/", $cookieDomain);
    setcookie("ksa_cs", "", 1, "/", $cookieDomain);
}

function setCookiesForLogin($cookieDomain, $client_id, $client_secret)
{
    $ksa_token = $_SESSION["ksa_token"];

    /** @TODO Add valid expiration times below! */
    setcookie("ksa_at", $ksa_token['access_token'], null, "/", $cookieDomain);
    setcookie("ksa_rt", $ksa_token['refresh_token'], null, "/", $cookieDomain);
    setcookie("ksa_s", $ksa_token['scope'], null, "/", $cookieDomain);
    setcookie("ksa_tt", $ksa_token['token_type'], null, "/", $cookieDomain);
    setcookie("ksa_cid", $client_id, null, "/", $cookieDomain);
    setcookie("ksa_cs", $client_secret, null, "/", $cookieDomain);
}

?>