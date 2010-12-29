<?php

/**
 * PluginsfFacebookGraphUserProfile
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    sfFacebookGraphPlugin
 * @subpackage userProfile
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
abstract class PluginsfFacebookGraphUserProfile
extends BasesfFacebookGraphUserProfile
{
  /**
   * Whether a user has just registered via facebook
   *
   * @var   boolean
   */
  protected $_newUser = false;

  /**
   * Create a user for a Facebook account
   *
   * Based on and borrowed heavily from
   * sfFacebookGuardAdapter::createSfGuardUserWithFacebookUidAndCon in
   * sfFacebookConnectPlugin by Fabrice Bernhard
   *
   * @param   int     $facebookUid
   * @param   string  $accessToken
   * @param   int     $accessTokenExpiry
   * @param   array   $facebookUserInfo
   * @return  sfGuardUser
   */
	static public function createUser($facebookUid, $accessToken, $accessTokenExpiry, array $facebookUserInfo) {
		try {
			$sfGuardUser = parent::createUser($facebookUid, $accessToken, $accessTokenExpiry, $facebookUserInfo);
		}
		catch (Exception $e) {
			throw $e;
		}

		$country = "US";
		if (sfConfig::get('sf_logging_enabled')) {
			sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: facebookUserInfo:' . var_export($facebookUserInfo, true));
		}
		if (isset($facebookUserInfo['locale'])) {
			$locale = $facebookUserInfo['locale'];
			$countryUser = explode('_', $locale);
			if (isset($countryUser[1])) {
				try {
					$countryCheck = sfCultureInfo::getInstance()->getCountries(array($countryUser[1]));
					if (array_key_exists($countryUser[1], $countryCheck)) {
						$country = $countryUser[1];
					}
				}
				catch (InvalidArgumentException $e) {}
			}
		}
		$sfGuardUser->setCountry($country);
		$sfGuardUser->save();
		return $sfGuardUser;
	}
  
  /**
   * Takes the request and tries to connect the user to an account
   *
   * @param   sfFacebookGraphUser  $user
   * @return  sfFacebookGraphUser
   */
	static public function getCurrentFacebookUser($user)
	{
		// get facebook user
		$facebookUid = sfFacebookGraph::getCurrentUser();

		if (!$facebookUid) {
			throw new Exception('Facebook user not found');
		}

		// get current user info from api
		try {
			$facebookUserInfo = sfFacebookGraph::getCurrentUserInfo();
		} catch (Exception $e) {
			throw $e;
		}

		$accessToken = sfFacebookGraph::getCurrentAccessToken();
		$accessTokenExpiry = sfFacebookGraph::getCurrentAccessExpiry();

		if (sfConfig::get('sf_logging_enabled')) {
			sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: user authenticated: ' . $user->isAuthenticated() ? 'yes' : 'no');
		}
		
		if ($user->isAuthenticated()) {
			if ($user->isFacebookConnected()) {
				if (sfConfig::get('sf_logging_enabled')) {
					sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: user already signed in');
				}
				// user already signed in
				$user->getProfile()->_facebookUpdateProfile(
					$accessToken,
					$accessTokenExpiry,
					$facebookUserInfo,
					$user->getGuardUser()
				);

			}
			else if($user->getProfile()->getFacebookUid() === null) {
				if (sfConfig::get('sf_logging_enabled')) {
					sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: user account exists but not connected to facebook');
				}
				// user account exists but not connected to facebook
				$user
					->getProfile()
					->_connectToFacebook(
						$facebookUid,
						$accessToken,
						$accessTokenExpiry,
						$facebookUserInfo,
						$user->getGuardUser()
					);

			}
			else {
				// user connected to different facebook account
				// sign them out (we'll try sign them in again)
				$user->signOut();

			}
		}

		// check if user exists
		if ($user->isAnonymous()) {
			if (sfConfig::get('sf_logging_enabled')) {
				sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: check if user exists');
			}
			$userObj = self::getUserByFacebookUid($facebookUid);

			if ($userObj) {
				$user->signIn($userObj, false, null, true);
			}
		}

		// check if a user with the username exists
		if ($user->isAnonymous()) {
			if (sfConfig::get('sf_logging_enabled')) {
				sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: check if a user with the username exists');
			}
			$userObj = self::getUserByFacebookUsername($facebookUid);

			if ( !$userObj) {
				// check by email address
				$email = isset($facebookUserInfo['email']) ? $facebookUserInfo['email'] : '';

				if (sfConfig::get('app_facebook_dont_store_proxy_emails', false)) {
					if (sfFacebookGraph::checkProxyEmail($email)) {
						$email = '';
					}
				}
				
				if (sfConfig::get('sf_logging_enabled')) {
					sfContext::getInstance()->getLogger()->debug(sprintf('Email by Proxy: %s', $email));
				}
					
				$userObj = self::getUserByEmail($email);
			}

			if ($userObj) {
				if (!$userObj->getProfile()) {
					// profile is null
					$profileClass = sfConfig::get('app_facebook_profile_class', 'sfFacebookGraphUserProfile');
		            $profile = new $profileClass();
		            $profile->setUser($userObj);
		            $userObj->setProfile($profile);
				}

				$userObj
					->getProfile()
					->_connectToFacebook(
						$facebookUid,
						$accessToken,
						$accessTokenExpiry,
						$facebookUserInfo,
						$userObj
					);
				$user->signIn($userObj, false, null, true);
			}
		}

		// if nothing exists create a new account
		if ($user->isAnonymous()) {
			if (sfConfig::get('sf_logging_enabled')) {
				sfContext::getInstance()->getLogger()->debug('{sfFacebookGraphUserProfile}: createUser');
			}
			try {
				$user->signIn(self::createUser(
					$facebookUid,
					$accessToken,
					$accessTokenExpiry,
					$facebookUserInfo
					), false, null, true
				);

			}
			catch (Exception $e) {
				throw $e;
			}
		}

		return $user;
	}  
  /**
   * Retrieve a user by their facebook uid
   *
   * @param   int  $facebookUid
   * @return  sfGuardUser|false
   */
  static public function getUserByFacebookUid($facebookUid)
  {
    return Doctrine::getTable('sfGuardUser')
                   ->createQuery('u')
                   ->innerJoin('u.Profile p')
                   ->where('p.facebook_uid = ?', $facebookUid)
                   ->fetchOne();
  }

  /**
   * Retrieve a user by their facebook username
   *
   * @param   int  $facebookUid
   * @return  sfGuardUser|false
   */
  static public function getUserByFacebookUsername($facebookUid)
  {
    return Doctrine::getTable('sfGuardUser')
      ->createQuery('u')
      ->leftJoin('u.Profile p')
      ->where('u.username = ?', self::generateFacebookUsername($facebookUid))
      ->fetchOne()
    ;
  }

  /**
   * Retrieve a user by their email address
   *
   * @param   string  $email
   * @return  sfGuardUser|false
   */
  static public function getUserByEmail($email)
  {
    if (!$email)
    {
      return false;
    }

    return Doctrine::getTable('sfGuardUser')
      ->createQuery('u')
      ->leftJoin('u.Profile p')
      ->where('u.email_address = ?', $email)
      ->fetchOne()
    ;
  }

  /**
   * Connect a user profile to a facebook account
   *
   * @param   int         $facebookUid
   * @param   string      $accessToken
   * @param   int         $accessTokenExpiry
   * @param   array       $facebookUserInfo
   * @param   sfGuardUser $user
   * @return  self
   */
  protected function _connectToFacebook(
    $facebookUid,
    $accessToken,
    $accessTokenExpiry,
    array $facebookUserInfo,
    sfGuardUser $user
  )
  {
    $this
      ->setFacebookUid($facebookUid)
      ->_facebookUpdateProfile(
        $accessToken, $accessTokenExpiry, $facebookUserInfo, $user
      )
    ;

    return $this;
  }

  /**
   * Update facebook details
   *
   * @param   string      $accessToken
   * @param   int         $accessTokenExpiry
   * @param   array       $facebookUserInfo
   * @param   sfGuardUser $user
   * @return  self
   */
  protected function _facebookUpdateProfile(
    $accessToken, $accessTokenExpiry, array $facebookUserInfo, sfGuardUser $user
  )
  {
    $this
      ->setActiveAccessToken($accessToken, $accessTokenExpiry)
      ->mergeFacebookInfo($facebookUserInfo, $user)
      ->save()
    ;

    return $this;
  }

  /**
   * Set and store the access token if the user has given us permissions to use
   * it
   *
   * @param   string  $accessToken        OAuth Access Token
   * @param   int     $accessTokenExpiry  Timestamp when access token will expire
   * @return  self
   */
  public function setActiveAccessToken($accessToken, $accessTokenExpiry)
  {
    $this->setAccessToken($accessTokenExpiry == 0 ? $accessToken : null);

    return $this;
  }

  /**
   * Merge a users data with that from Facebook, updating fields where
   * appropriate
   *
   * @param   array       $facebookUserInfo
   * @param   sfGuardUser $user
   * @return  self
   */
  public function mergeFacebookInfo(array $facebookUserInfo, sfGuardUser $user)
  {
    if (!$this->getUserSetName()) {

      if (isset($facebookUserInfo['name'])
      && $this->getFullName() != $facebookUserInfo['name']) {
        $this->setFullName($facebookUserInfo['name']);
      }

      if (isset($facebookUserInfo['first_name'])
      && $user->getFirstName() != $facebookUserInfo['first_name']) {
        $user->setFirstName($facebookUserInfo['first_name']);
      }

      if (isset($facebookUserInfo['last_name'])
      && $user->getLastName() != $facebookUserInfo['last_name']) {
        $user->setLastName($facebookUserInfo['last_name']);
      }

    }

    if (!$this->getUserSetEmailAddress()) {
      $email = isset($facebookUserInfo['email'])
        ? $facebookUserInfo['email']
        : '';

      if (sfConfig::get('app_facebook_dont_store_proxy_emails', false)) {
        if (sfFacebookGraph::checkProxyEmail($email)) {
          $email = '';
        }
      }

      if ($email != $user->getEmailAddress()) {
        $user->setEmailAddress($email);
      }
    }

    return $this;
  }

  /**
   * Generates the username used in sfGuard
   *
   * @param   int $facebookUid
   * @return  string
   */
  static public function generateFacebookUsername($facebookUid)
  {
    return 'Facebook_' . $facebookUid;
  }

  /**
   * Get Facebook logout url for user
   *
   * @param   string $redirect
   *
   * @return  string
   */
  public function getFacebookLogoutUrl($redirect = '')
  {
    $params = array();

    if ($redirect)
    {
      $params['next'] = $redirect;
    }

    return sfFacebookGraph::getFacebookPlatform()->getLogoutUrl($params);
  }

  /**
   * Get whether or not a new user has been registered
   *
   * @return  bool
   */
  public function getNewUser()
  {
    return $this->_newUser;
  }

  /**
   * Set whether or not a new user has been registered
   *
   * @param   bool  $newUser
   * @return  self
   */
  public function setNewUser($newUser)
  {
    $this->_newUser = $newUser;

    return $this;
  }
}