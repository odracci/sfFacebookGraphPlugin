<?php
/** 
 * Filter to process whether a user is logged in with facebook
 * 
 * @package     sfFacebookGraphPlugin
 * @subpackage  loginStatus
 * @author      Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfFacebookGraphLoginStatusFilter extends sfFilter
{
	/**
	 * Executes the filter chain.
	 *
	 * @param sfFilterChain $filterChain
	 */
	public function execute($filterChain) {
		$facebookUid = sfFacebookGraph::getCurrentUser();
		$user = $this->context->getUser();

		// check for logged in user
		if ($facebookUid && !$user->isFacebookConnected()) {
			try {
				sfFacebookGraphUserProfile::getCurrentFacebookUser($user);
			}
			catch (Exception $e) {
				if (sfConfig::get('sf_logging_enabled')) {
					sfContext::getInstance()->getLogger()->info('{sfFacebookGraphLoginStatusFilter} Error logging in ' . $e->getMessage());
				}
			}
		}

		// check for logged out
		if ($user->isFacebookAuthenticated() && !$user->isFacebookConnected()) {
			$user->signOut();
		}

		$filterChain->execute();
	}
}


