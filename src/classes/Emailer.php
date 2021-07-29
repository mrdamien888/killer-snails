<?php
namespace KillerSnailsAccounts;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Emailer {
	public static function newRegisteredUser($userEmail, $userRealName, $verifyLink) {
		$date = date_create("",timezone_open("America/New_York"));
		$time= $date->format('Y-m-d H:i:s');

		// HTML version
		$htmlBody  = "<b>Welcome $userRealName!</b><br><br>";
		$htmlBody .= "Thank you for signing up for a Killer Snails account.<br><br>";
		$htmlBody .= "Please verify your account by clicking on this link:<br>";
		$htmlBody .= '<a href="' . $verifyLink . '" style="color: #FF0000;">' . $verifyLink . '</a>'. "<br><br>";
		$htmlBody .= "Request Date: <i>$time (ET)</i><br>";

		// Plain version
		$plainBody  = "Welcome $userRealName!\n\n";
		$plainBody .= "Thank you for signing up for a Killer Snails account.\n\n";
		$plainBody .= "Please verify your account by visiting this link:\n";
		$plainBody .= "\t" . $verifyLink . "\n\n";
		$plainBody .= "Request Date: $time (ET)\n";

		return Emailer::SendMail("Verify your Killer Snails Account", $htmlBody, $plainBody, $userEmail, $userRealName);
	}

	public static function newEnrolledUser($userEmail, $userRealName, $courseTitle, $courseTeacher, $verifyLink) {
		$date = date_create("",timezone_open("America/New_York"));
		$time= $date->format('Y-m-d H:i:s');

		// HTML version
		$htmlBody  = "<b>Welcome $userRealName!</b><br><br>";
		$htmlBody .= "You have been enrolled in the course &quot;" . $courseTitle . "&quot; by your teacher, " .$courseTeacher . ".<br><br>";
		$htmlBody .= "This course uses a Killer Snails account, which has been created for you. However, you will need to use this link to login and create a password:<br>";
		$htmlBody .= '<a href="' . $verifyLink . '" style="color: #FF0000;">' . $verifyLink . '</a>'. "<br><br>";
		$htmlBody .= "Request Date: <i>$time (ET)</i><br>";

		// Plain version
		$plainBody  = "Welcome $userRealName!\n\n";
		$plainBody .= "You have been enrolled in a class that is using a Killer Snails account.\n\n";
		$plainBody .= "This course uses a Killer Snails account, which has been created for you. However, you will need to use this link to login and create a password:\n";
		$plainBody .= "\t" . $verifyLink . "\n\n";
		$plainBody .= "Request Date: $time (ET)\n";

		return Emailer::SendMail("Welcome, you are invited to a class \"$courseTitle\"", $htmlBody, $plainBody, $userEmail, $userRealName);
	}

	public static function enrolledUser($userEmail, $userRealName, $courseTitle, $courseTeacher) {
		$date = date_create("",timezone_open("America/New_York"));
		$time= $date->format('Y-m-d H:i:s');

		// HTML version
		$htmlBody  = "<b>Welcome $userRealName!</b><br><br>";
		$htmlBody .= "You have been enrolled in the course &quot;" . $courseTitle . "&quot; by your teacher, " .$courseTeacher . ".<br><br>";
		$htmlBody .= "Request Date: <i>$time (ET)</i><br>";

		// Plain version
		$plainBody  = "Welcome $userRealName!\n\n";
		$plainBody .= "You have been enrolled in a class that is using a Killer Snails account.\n\n";
		$plainBody .= "Request Date: $time (ET)\n";

		return Emailer::SendMail("You are invited to a class \"$courseTitle\"", $htmlBody, $plainBody, $userEmail, $userRealName);
	}

	public static function forgotPassword($userEmail, $userRealName, $resetLink, $date) {
		$requestTime= $date->format('Y-m-d H:i:s');
		$expTime = $date->modify("+4 hours")->format('Y-m-d H:i:s');

		// HTML version
		$htmlBody  = "<b>$userRealName,</b><br><br>";
		$htmlBody .= "A password reset was requested for your account.<br><br>";
		$htmlBody .= "If you forgot your password, please click on this link to reset it:<br>";
		$htmlBody .= '<a href="' . $resetLink . '" style="color: #FF0000;">' . $resetLink . '</a>'. "<br><br>";
		$htmlBody .= "This link is valid for only 4 hours.<br><br>";
		$htmlBody .= "If you did not request this, you can ignore it or log in to invalidate this request.<br>";
		$htmlBody .= "Expiration Time: <i>$expTime (ET)</i><br>";
		$htmlBody .= "Request Date: <i>$requestTime (ET)</i><br>";

		// Plain version
		$plainBody  = "$userRealName,\n\n";
		$plainBody .= "A password reset was requested for your account.\n\n";
		$plainBody .= "If you forgot your password, please visit this link to reset it\n";
		$plainBody .= "\t" . $resetLink . "\n\n";
		$plainBody .= "This link is valid for only 4 hours.\n\n";
		$plainBody .= "If you did not request this, you can ignore it or log in to invalidate this request.\n";
		$plainBody .= "Expiration Time : $expTime (ET)\n\n";
		$plainBody .= "Request Date: $requestTime (ET)\n";

		return Emailer::SendMail("Reset your Killer Snails Account password", $htmlBody, $plainBody, $userEmail, $userRealName);
	}

	protected static function SendMail($subject, $htmlBody, $plainBody, $userEmail, $userName) {
		$mail = new PHPMailer(true);
		try {
			if($_SERVER['REMOTE_ADDR']!="127.0.0.1" && $_SERVER['REMOTE_ADDR']!="::1") {
				$mail->IsSendmail();
			} else {
				$mail->isSMTP();
				//$mail->SMTPDebug = 1;
				$mail->SMTPAuth   = true;
				$mail->SMTPSecure = 'ssl';
				$mail->Port       = 465;
				/** @NOTE
				 * Until I figure how to configure MAMP, define your email login credential in
				 * in an emailer-secret.php file: It just needs to contain:
				 * $host, $username, and $password
				*/
				require realpath( dirname( __FILE__ ) ) . "/emailer-secret.php";
				$mail->Host       = $host;
				$mail->Username   = $username;
				$mail->Password   = $password;
			}
			$mail->AddAddress($userEmail,$userName);
			$mail->SetFrom("no-reply@developer_test","Killer Snails Account");
			$mail->AddReplyTo("no-reply@developer_test","No Reply");
			$mail->Subject = $subject;

			// All the heavy lifting
			$mail->Body = Emailer::wrapHtmlBodyInHeaderAndFooter($htmlBody);
			$mail->AltBody = $plainBody;
			// Send Mail, and return if there was an error!
			if(!$mail->Send()) {
				return "FAILED: {" . $mail->ErrorInfo . "}";
			} else {
				return "Mail sent";
			}
		} catch (Exception $e) {
			return "FAILED: {" . $e . "}";
		}
	}

	private static function wrapHtmlBodyInHeaderAndFooter($htmlBody) {
		return Emailer::emailHeader() . $htmlBody . Emailer::emailFooter();
	}

	private static function emailHeader() {
		$html  = '<body style="background-color: #81C0C5; color: #FFFFFF; font-family: Verdana, Arial, Helvetica, sans-serif; margin: 0; padding: 0;">';
		$html .= '<div style="padding: 15px;">';
		return $html;
	}

	private static function emailFooter() {
		$html  = '<br></div>';
		$html .= '</body>';
		return $html;
	}
}
?>