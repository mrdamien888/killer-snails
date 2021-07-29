<?php
/** @var $stmt PDOStatement */
namespace KillerSnailsAccounts;

use OAuth2\Storage\Pdo;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Doctrine\Common\Annotations\Annotation\Target;

class ksaPDO extends Pdo {
	/**
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public function checkUserCredentials($username, $password) {
		if ($user = $this->getUser($username)) {
			if($this->checkPassword($user, $password)) {
				$this->clearPasswordReset($username);
				return true;
			}
		}
		return false;
	}

	protected function checkPassword($user, $password) {
		return password_verify($password, $user["password"]);
	}

	// use a secure hashing algorithm when storing passwords. Override this for your application
	protected function hashPassword($password) {
		return password_hash($password, PASSWORD_BCRYPT);
	}

	/**
	 * @param string $username Really the email of user
	 * @return array|bool
	 */
	public function getUser($username) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT * from %s where email=:email', $this->config['user_table']));
		$stmt->execute(array('email' => $username));

		if (!$userInfo = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return false;
		}

		return array_merge(array(
			'user_id' => $userInfo["username"]
		), $userInfo);
	}

	public function getEmail($username) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT email from %s where username=:username', $this->config['user_table']));
		$stmt->execute(array('username' => $username));

		if (!$userInfo = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return false;
		}

		return $userInfo["email"];
	}

	public function isUserVerified($username) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT email_verified from %s where username=:username', $this->config['user_table']));
		$stmt->execute(compact("username"));

		if (!$userInfo = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return false;
		}

		return $userInfo["email_verified"];
	}

	public function isUserPasswordEmpty($email) {
		return $this->checkPassword($username, "NONE");
	}
	/**
	 * @param string $username Which is really their email address
	 * @param string $password
	 * @param string $firstName Not used on updates (should use update profile!)!
	 * @param string $lastName Not used on updates (should use update profile!)!
	 * @return bool
	 */
	public function setUser($username, $password, $firstName = null, $lastName = null, $enrolledByTeacher = false, $courseTitle = "", $courseTeacher = "") {
		// do not store in plaintext
		$password = $this->hashPassword($password);

		$user =  $this->getUser($username);
		$time = time();

		if ($user!==false) {
			$this->clearPasswordReset($username);
			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET password=:password where email=:username', $this->config['user_table']));
		} else {
			$uuid = number_format( hexdec( str_replace(".", "", uniqid("",true)) ) , 0, "", "");
			if($enrolledByTeacher) {
				$verificationCode = $this->sendEnrolledNewUserCode($username, $uuid, $firstName, $lastName, $courseTitle, $courseTeacher, $time);
			} else {
				$verificationCode = $this->sendVerificationCode($username, $uuid, $firstName, $lastName, $time);
			}
			$stmt = $this->db->prepare(sprintf('INSERT INTO %s (email, password, username, email_verified) VALUES (:username, :password, "%s", 0)', $this->config['user_table'], $uuid));
			$this->setProfile($uuid, $firstName, $lastName, $verificationCode);
			if($enrolledByTeacher) {
				$this->setProfileTeacherStudentStatus($uuid,"0","1");
			} else {
				$this->setProfileTeacherStudentStatus($uuid,"1","0");
			}
		}

		return $stmt->execute(compact('username', 'password'));
	}

	public function sendVerificationCode($username, $uuid, $firstName, $lastName, $time) {
		$verificationBase = $this->getBaseDirectory() . "verify/";
		$verificationCode = md5($time);
		$secret = base64_encode($this->base_convert((string)$time,10,36) . ":" . $this->base_convert($uuid,10,36));
		$secret = str_replace('=', '', $secret);
		Emailer::newRegisteredUser($username, $firstName . " " . $lastName, $verificationBase . $secret);
		return $verificationCode;
	}

	public function sendEnrolledNewUserCode($username, $uuid, $firstName, $lastName, $courseTitle, $courseTeacher, $time) {
		$verificationBase = $this->getBaseDirectory() . "enrolled/";
		$verificationCode = md5($time);
		$secret = base64_encode($this->base_convert((string)$time,10,36) . ":" . $this->base_convert($uuid,10,36));
		$secret = str_replace('=', '', $secret);
		Emailer::newEnrolledUser($username, $firstName . " " . $lastName, $courseTitle, $courseTeacher, $verificationBase . $secret);
		return $verificationCode;
	}

	public function resendVerification($username) {
		$user = $this->getUser($username);
		if($user!==false) {
			$profile = $this->getProfile($user["user_id"]);

			$time = time();
			$verificationCode = $this->sendVerificationCode($username, $user["user_id"], $profile["first_name"], $profile["last_name"], $time);

			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET verification_code=:verificationCode WHERE username=:username', "user_profile"));
			return $stmt->execute(array('verificationCode' => $verificationCode, 'username' => $user["user_id"]));
		} else {
			return false;
		}
	}

	public function verifyEmail($username, $verification_code, $setVerified = true) {
		$stmt = $this->db->prepare("SELECT EXISTS(SELECT * FROM user_profile WHERE username=:username AND verification_code=:verification_code) AS result");
		$stmt->execute(compact('username','verification_code'));
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		if($result["result"]=="1") {
			if($setVerified==true) {
				$this->emailVerified($username);
			}
			return true;
		} else {
			return false;
		}
	}

	protected function clearPasswordReset($username) {
		$user = $this->getUser($username);
		$stmt = $this->db->prepare($sql = sprintf("UPDATE %s SET reset_code=NULL, reset_timeout=NULL WHERE username=:username",'user_profile'));
		$stmt->execute(array('username' => $user["user_id"]));
	}

	protected function emailVerified($username) {
		$stmt = $this->db->prepare($sql = sprintf("UPDATE %s SET email_verified=1 WHERE username=:username",$this->config['user_table']));
		$stmt->execute(array('username' => $username));
	}

	public function forgotPassword($username) {
		$user = $this->getUser($username);
		if($user!==false) {
			$profile = $this->getProfile($user["user_id"]);
			$timeout = date('Y-m-d G:i:s', strtotime("+4 hours"));
			$resetCode = $this->sendPasswordResetCode($username, $user["user_id"], $profile["first_name"], $profile["last_name"], time());
			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET reset_code=:resetCode, reset_timeout=:timeout WHERE username=:username', "user_profile"));
			return $stmt->execute(array('resetCode' => $resetCode, 'username' => $user["user_id"], 'timeout' => $timeout));
		} else {
			return false;
		}
	}

	public function verifyPasswordReset($username, $reset_code) {
		$stmt = $this->db->prepare("SELECT reset_timeout FROM user_profile WHERE username=:username AND reset_code=:reset_code");
		$stmt->execute(compact('username','reset_code'));
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		if($result!==false) {
			$now = time();
			$target = strtotime($result["reset_timeout"]);
			$diff = $now - $target;

			if($diff<1) {
				$email = $this->getEmail($username);
				$user = $this->getUser( $email );
				return $user;
			}
		}
		return false;
	}

	public function sendPasswordResetCode($username, $uuid, $firstName, $lastName, $time) {
		$verificationBase = $this->getBaseDirectory() . "reset_password/";
		$verificationCode = md5($time);
		$secret = base64_encode($this->base_convert((string)$time,10,36) . ":" . $this->base_convert($uuid,10,36));
		$secret = str_replace('=', '', $secret);
		$date = date_create("",timezone_open("America/New_York"));
		Emailer::forgotPassword($username, $firstName . " " . $lastName, $verificationBase . $secret, $date);
		return $verificationCode;
	}

	private function getBaseDirectory() {
		$base = "https://" . $_SERVER["SERVER_NAME"] .  "/";
		if($_SERVER['REMOTE_ADDR']=="127.0.0.1" || $_SERVER['REMOTE_ADDR']=="::1") {
			$base = "http://localhost:8888/developer_test/";
		}
		return $base;
	}

	/* ---- Profile ---- */
	/** @TODO Update verification code */
	public function setProfile($username, $firstName, $lastName, $verificationCode = "", $isTeacher = "", $isStudent = "") {
		if ($this->getUser($username)) {
			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET first_name=:firstName, last_name=:lastName where username=:usernam', 'user_profile'));
		} else {
			/** @TODO Send email, set email_verified to false! */
			$stmt = $this->db->prepare(sprintf('INSERT INTO %s (username, first_name, last_name, verification_code) VALUES (:username, :firstName, :lastName, :verificationCode)', 'user_profile'));
		}
		return $stmt->execute(compact('username', 'firstName', 'lastName', 'verificationCode'));
	}

	public function setProfileTeacherStudentStatus($username, $isTeacher, $isStudent) {
		if ($this->getUser($username)) {
			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET is_teacher=%s, is_student=%s where username=:usernam', 'user_profile', $isTeacher, $isStudent));
			return $stmt->execute(compact('username'));
		}
		return false;
	}

	public function updateProfile($username, $firstName, $lastName, $avatar, $schoolName) {
		$stmt = $this->db->prepare(sprintf('UPDATE %s SET first_name=:firstName, last_name=:lastName, avatar=:avatar, school_name=:schoolName WHERE username=:username', 'user_profile'));
		return $stmt->execute(compact('username', 'firstName', 'lastName', 'avatar', 'schoolName'));
	}

	public function getProfile($username) {
		/** @NOTE only return certain values for user_profile */
		$stmt = $this->db->prepare($sql = sprintf('SELECT user_profile.username, user_profile.avatar, user_profile.school_name, user_profile.first_name, user_profile.last_name, %s.email FROM %s JOIN %s USING(username) WHERE username=:username',
													$this->config['user_table'], "user_profile", $this->config['user_table']));
		$stmt->execute(array('username' => $username));
		if (!$userInfo = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return false;
		}
		return $userInfo;
	}

	/* ---- Courses ---- */
	public function getCourses($userId) {
		$teaching = $this->getCoursesTeaching($userId);
		if($teaching===false) {
			$teaching = array();
		}

		$enrolled = $this->getCoursesEnrolledIn($userId);
		if($enrolled===false) {
			$enrolled = array();
		}

		return array_merge($teaching,$enrolled);
	}

	public function getCoursesTeaching($userId) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT * from %s where owner_id=:userId', "biodive_courses"));
		$stmt->execute(array('userId' => $userId));
		if (!$courses = $stmt->fetchAll(\PDO::FETCH_ASSOC)) {
			return false;
		}
		return $courses;
	}

	public function getCoursesEnrolledIn($userId) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT biodive_courses.*, biodive_students.course_id FROM biodive_courses JOIN %s USING(course_id) where student_id=:userId', "biodive_students"));
		$stmt->execute(array('userId' => $userId));

		if (!$courses = $stmt->fetchAll(\PDO::FETCH_ASSOC)) {
			return false;
		}
		return $courses;
	}

	public function getCourse($courseId) {
		$stmt = $this->db->prepare($sql = sprintf('SELECT * from %s where course_id=:courseId', "biodive_courses"));
		$stmt->execute(array('courseId' => $courseId));

		if (!$courseInfo = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return false;
		}
		// Changing the default behavior is to use "username" as the user_id
		return $courseInfo;
	}

	public function setCourse($owner, $title, $courseId = "") {
		// Should check if it exists?
		if ($courseId=="") {
			$courseId = number_format( hexdec( str_replace(".", "", uniqid("",false)) ) , 0, "", "");
			$stmt = $this->db->prepare(sprintf('INSERT INTO %s (owner_id, course_id, title) VALUES (:owner, :courseId, :title)', 'biodive_courses'));
			if(!$stmt->execute(compact('owner', 'courseId', 'title'))) {
				return false;
			}
			return $courseId;
		} else {
			$stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET first_name=:firstName, last_name=:lastName where username=:usernam', 'biodive_courses'));
			return $stmt->execute(compact('owner', 'courseId', 'title'));
		}
	}

	/* ---- Students ---- */
	public function addStudentToCourse($courseId, $studentEmail, $firstName, $lastName, $courseTitle, $courseTeacher) {
		$results = false;
		if ($courseId!="") {
			$student = $this->getUser($studentEmail);
			if($student===false) {
				$setUser = $this->setUser($studentEmail, "NONE", $firstName, $lastName, true, $courseTitle, $courseTeacher);
				if($setUser===false) {
					return false;
				}
				$student = $this->getUser($studentEmail);
			} else {
				$profile = $this->getProfile($student["username"]);
				Emailer::enrolledUser($studentEmail, $profile["first_name"] . " " . $profile["last_name"], $courseTitle, $courseTeacher);
			}
			$studentId = $student["username"];
			$stmt = $this->db->prepare(sprintf('INSERT INTO %s (student_id, course_id) VALUES (:studentId, :courseId)', 'biodive_students'));
			if(!$stmt->execute(compact('studentId', 'courseId'))) {
				$results = false;
			}
		}
		return $results;
	}

	public function getStudentsEnrolledInCourse($courseId) {
		/** @TODO Check if current user is owner of student... */
		$sql = 'SELECT bs.student_id as id, up.first_name, up.last_name, up.avatar, oa.email
				FROM biodive_students bs
				JOIN user_profile up ON up.username = bs.student_id
				JOIN oauth_users oa ON bs.student_id = oa.username
				WHERE course_id=:courseId';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array('courseId' => $courseId));

		if (!$students= $stmt->fetchAll(\PDO::FETCH_ASSOC)) {
			return false;
		}

		// Changing the default behavior is to use "username" as the user_id
		return $students;
	}

	public function getTeacherForCourse($courseId) {
		/** @TODO Check if current user is owner of student... */
		$sql = 'SELECT bc.owner_id id, up.first_name, up.last_name, up.avatar, oa.email
				FROM biodive_courses bc
				JOIN user_profile up ON up.username = bc.owner_id
				JOIN oauth_users oa ON bc.owner_id = oa.username
				WHERE course_id=:courseId';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array('courseId' => $courseId));

		if (!$teachers= $stmt->fetchAll(\PDO::FETCH_ASSOC)) {
			return false;
		}

		// Changing the default behavior is to use "username" as the user_id
		return $teachers;
	}

	/* ---- Utility Functions ---- */
	/* Follows the syntax of base_convert (http://www.php.net/base_convert)
	 * Created by Michael Renner @ http://www.php.net/base_convert 17-May-2006 03:24
	 * His comment is has since been deleted. The function will tell you why.
	 */
	static public function base_convert($numstring, $frombase, $tobase) {
		$chars = "0123456789abcdefghijklmnopqrstuvwxyz";
		$tostring = substr($chars, 0, $tobase);

		$length = strlen($numstring);
		$result = '';
		for ($i = 0; $i < $length; $i++) {
			$number[$i] = strpos($chars, $numstring[$i]);
		}
		do {
			$divide = 0;
			$newlen = 0;
			for ($i = 0; $i < $length; $i++) {
				$divide = $divide * $frombase + $number[$i];
				if ($divide >= $tobase) {
					$number[$newlen++] = (int)($divide / $tobase);
					$divide = $divide % $tobase;
				} elseif ($newlen > 0) {
					$number[$newlen++] = 0;
				}
			}
			$length = $newlen;
			$result = $tostring[$divide] . $result;
		} while ($newlen != 0);
		return $result;
	}

	/**
	 * DDL to create OAuth2 database and tables for PDO storage
	 *
	 * @see https://github.com/dsquier/oauth2-server-php-mysql
	 *
	 * @param string $dbName
	 * @return string
	 */
	public function getBuildSql($dbName = 'oauth2_server_php') {
		$dbName = "developer_test";

		$sql = "
			CREATE TABLE {$this->config['client_table']} (
				client_id             VARCHAR(80)   NOT NULL,
				client_secret         VARCHAR(80),
				redirect_uri          VARCHAR(2000),
				grant_types           VARCHAR(80),
				scope                 VARCHAR(4000),
				user_id               VARCHAR(80),
				PRIMARY KEY (client_id)
			);

			CREATE TABLE {$this->config['access_token_table']} (
				access_token         VARCHAR(40)    NOT NULL,
				client_id            VARCHAR(80)    NOT NULL,
				user_id              VARCHAR(80),
				expires              TIMESTAMP      NOT NULL,
				scope                VARCHAR(4000),
				PRIMARY KEY (access_token)
			);

			CREATE TABLE {$this->config['code_table']} (
				authorization_code  VARCHAR(40)    NOT NULL,
				client_id           VARCHAR(80)    NOT NULL,
				user_id             VARCHAR(80),
				redirect_uri        VARCHAR(2000),
				expires             TIMESTAMP      NOT NULL,
				scope               VARCHAR(4000),
				id_token            VARCHAR(1000),
				PRIMARY KEY (authorization_code)
			);

			CREATE TABLE {$this->config['refresh_token_table']} (
				refresh_token       VARCHAR(40)    NOT NULL,
				client_id           VARCHAR(80)    NOT NULL,
				user_id             VARCHAR(80),
				expires             TIMESTAMP      NOT NULL,
				scope               VARCHAR(4000),
				PRIMARY KEY (refresh_token)
			);

			CREATE TABLE {$this->config['user_table']} (
				username            VARCHAR(80),
				password            VARCHAR(80),
				first_name          VARCHAR(80),
				last_name           VARCHAR(80),
				email               VARCHAR(80),
				email_verified      BOOLEAN,
				scope               VARCHAR(4000)
			);

			CREATE TABLE {$this->config['scope_table']} (
				scope               VARCHAR(80)  NOT NULL,
				is_default          BOOLEAN,
				PRIMARY KEY (scope)
			);

			CREATE TABLE {$this->config['jwt_table']} (
				client_id           VARCHAR(80)   NOT NULL,
				subject             VARCHAR(80),
				public_key          VARCHAR(2000) NOT NULL
			);

			CREATE TABLE {$this->config['jti_table']} (
				issuer              VARCHAR(80)   NOT NULL,
				subject             VARCHAR(80),
				audiance            VARCHAR(80),
				expires             TIMESTAMP     NOT NULL,
				jti                 VARCHAR(2000) NOT NULL
			);

			CREATE TABLE {$this->config['public_key_table']} (
				client_id            VARCHAR(80),
				public_key           VARCHAR(2000),
				private_key          VARCHAR(2000),
				encryption_algorithm VARCHAR(100) DEFAULT 'RS256'
			);


			CREATE TABLE biodive_courses (
				id                   INT(11)         UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id            VARCHAR(20)     NOT NULL DEFAULT '',
				owner_id             VARCHAR(30)     DEFAULT NULL,
				title                TINYTEXT,
				PRIMARY KEY (id),
				UNIQUE KEY  course_id (course_id)
			);

			CREATE TABLE biodive_students (
				id                   INT(11)         unsigned NOT NULL AUTO_INCREMENT,
				course_id            VARCHAR(20)     DEFAULT NULL,
				student_id           VARCHAR(30)     DEFAULT NULL,
				quick_key            TINYINT(3)      DEFAULT '0',
				PRIMARY KEY (id)
			);

			CREATE TABLE four_char_shortcut (
				id                   MEDIUMINT(9)    unsigned NOT NULL AUTO_INCREMENT,
				shortcut             CHAR(4)         DEFAULT NULL,
				PRIMARY KEY (id)
			);

			CREATE TABLE user_profile (
				username             VARCHAR(80)     NOT NULL DEFAULT '',
				avatar               MEDIUMTEXT,
				school_name          MEDIUMTEXT,
				first_name           TINYTEXT,
				last_name            TINYTEXT,
				is_teacher           TINYINT(1)      DEFAULT NULL,
				is_student           TINYINT(1)      DEFAULT NULL,
				verification_code    VARCHAR(32)     DEFAULT NULL,
				reset_code           VARCHAR(32)     DEFAULT NULL,
				reset_timeout        TIMESTAMP       NULL DEFAULT NULL,
				time_created         DATETIME        DEFAULT CURRENT_TIMESTAMP,
				time_updated         DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (username)
			);";

		return $sql;
	}
}
?>
