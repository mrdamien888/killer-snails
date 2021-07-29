<?php
function writeHeader($title, $deeper = false)
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <title>Killer Snails Accounts: <?php echo $title; ?></title>
        <meta charset="utf-8">
        <meta name="description" content="Killer Snails Accounts: <?php echo $title; ?>">
        <!-- JQuery -->
        <script type="text/javascript" src="//code.jquery.com/jquery-2.2.4.min.js"
                integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
        <!-- Bootstrap 4 -->
        <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.3/umd/popper.min.js"
                integrity="sha384-vFJXuSJphROIrBnz7yo7oB41mKfc8JzQZiCq4NCceLEaO4IHwicKwpJf9c9IpFgh"
                crossorigin="anonymous"></script>
        <script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/js/bootstrap.min.js"
                integrity="sha384-alpBpkh1PFOepccYVYDB4do5UnbKysX5WZXm3XxPqe5iKTfUKjNkCk9SaVuEZflJ"
                crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css"
              integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb"
              crossorigin="anonymous">
        <link rel="stylesheet" href="<?php if ($deeper == true) {
            echo "../";
        } ?>assets/css/styles.css">
        <script type="text/javascript" src="<?php if ($deeper == true) {
            echo "../";
        } ?>assets/js/index.js"></script>
    </head>
    <?php
}

function writeFooter()
{
    ?>
    <footer class="footer">
        <div class="row">
            <div class="col-sm-12 col-md-6 col-lg-6">
                <span class="copyright">&copy; <?php echo date("Y"); ?> Killer Snails LLC. All rights reserved.</span>
            </div>
            <div class="col-sm-12 col-md-6 col-lg-6">
                <ul class="list-inline">
                    <li class="list-inline-item">
                        <span class="link" role="button" data-toggle="modal" data-target="#info-modal"
                              data-title="Terms and Conditions" data-link="assets/includes/terms_and_conditions.html"/>Terms</span>
                    </li>
                    <li class="list-inline-item">
						<span class="link" role="button" data-toggle="modal" data-target="#info-modal"
                              data-title="Privacy Policy"
                              data-link="assets/includes/privacy_policy.html">Privacy Policy</a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>
    <div class="modal" tabindex="-1" role="dialog" id="info-modal">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="info-modal-title">__TITLE__</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="info-modal-body">__BODY__</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
}

function writeProfileHeader($profile ,$deeper = false)

{
    $path = $deeper? '../' : './';
    ?>

    <div class="row">
        <div class="col-md-2">
            <img src="<?php echo $path ?>assets/img/logo.png" alt="" class="w-100 mb-4">
        </div>
        <div class="col-md-8">
            <div class="mt-2">
                <h2 class="font-weight-bold"><strong>Welcome, <?php echo $profile["first_name"] . " " . $profile["last_name"]; ?></strong></h2>
                <h5>Email: <?php echo $profile["email"]; ?></h5>
                <h5>School: <?php echo $profile["school_name"]; ?></h5>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-right mt-2">
                <img src="<?php if(empty($profile["avatar"])) { echo $path . 'assets/img/user.png'; } else { echo $profile["avatar"]; } ?>" style="width:100px; height:100px; border-radius: 100%; border: 4px solid #ccc;"><br>
                <a href="update_profile" class="mt-1 d-block text-white font-weight-bold">Edit Profile</a>
            </div>
        </div>
    </div>

    <div style="height:6px; background-color: #ccc; margin-top: 15px; margin-left:-25px; margin-right: -25px;"></div>
    <?php
}

?>