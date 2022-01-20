<?php

use MediaWiki\Session\SessionManager;

class DeliberationSession {
	public static function touchSession() {
		$session = SessionManager::getGlobalSession();
		$collection = $session['wsDeliberation'];
		$collection['timestamp'] = wfTimestampNow();
		$session['wsDeliberation'] = $collection;
	}

	public static function enable() {
		$session = SessionManager::getGlobalSession();
		$session->persist();

		$session['wsDeliberation']['enabled'] = true;
		self::touchSession();
	}

	public static function disable() {
		$session = SessionManager::getGlobalSession();

		if ( !isset( $session['wsDeliberation'] ) ) {
			return;
		}
		$session['wsDeliberation']['enabled'] = false;
		self::touchSession();
	}

	public static function isEnabled() {
		$session = SessionManager::getGlobalSession();

		return isset( $session['wsDeliberation'] ) &&
			isset( $session['wsDeliberation']['enabled'] ) &&
			$session['wsDeliberation']['enabled'];
	}
}

?>
