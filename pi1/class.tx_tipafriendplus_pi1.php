<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007  <>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Plugin 'Tip-A-Friend Plus' for the 'tipafriend_plus' extension.
 *
 * @author     <>
 * @package    TYPO3
 * @subpackage    tx_tipafriendplus
 */
class tx_tipafriendplus_pi1 extends tslib_pibase {

	/**
	 * Same as class name
	 *
	 * @var string
	 */
	public $prefixId = 'tx_tipafriendplus_pi1';

	/**
	 * Path to this script relative to the extension dir
	 *
	 * @var string
	 */
	public $scriptRelPath = 'pi1/class.tx_tipafriendplus_pi1.php';

	/**
	 * The extension key
	 *
	 * @var string
	 */
	public $extKey = 'tipafriend_plus';

	/**
	 * @var boolean
	 */
	public $pi_checkCHash = TRUE;

	/**
	 * @var array
	 */
	protected $config = array();

	/**
	 * @var tx_srfreecap_pi2|NULL
	 */
	protected $freeCap = NULL;

	/**
	 * @var string
	 */
	protected $hmacSalt = 'tipafriend_plus';

	/**
	 * @var array
	 */
	protected $typolink_conf = array();

	/**
	 * @var string
	 */
	protected $templateCode = '';

	/**
	 * @var string
	 */
	protected $theCode = '';

	/**
	 * The main method of the PlugIn
	 *
	 * @param string $content The PlugIn content
	 * @param array $conf The PlugIn configuration
	 * @return string The content that is displayed on the website
	 */

	public function main($content, $conf) {

		// code inserted to use free Captcha
		if (t3lib_extMgm::isLoaded('sr_freecap')) {
			require_once(t3lib_extMgm::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		}

		$this->conf = $conf;
		$this->pi_initPIflexForm();
		$this->pi_loadLL();

		$this->config['code'] = $this->cObj->stdWrap($this->conf['code'], $this->conf['code.']);

		// template is read.
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// TYpoLink
		$this->typolink_conf = $this->conf['typolink.'];
		$this->typolink_conf['additionalParams'] = $this->cObj->stdWrap($this->typolink_conf['additionalParams'], $this->typolink_conf['additionalParams.']);
		unset($this->typolink_conf['additionalParams.']);

		$flexform_code = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'code_selector', 'sDEF');
		if (count($flexform_code)) {
			$codes = (array) $flexform_code;
		} else {
			$codes = t3lib_div::trimExplode(',', $this->config['code'] ? $this->config['code'] : $this->conf['defaultCode'], 1);
			if (!count($codes)) {
				$codes = array('');
			}
		}
		while (list(, $theCode) = each($codes)) {
			$theCode = (string) strtoupper(trim($theCode));
			$this->theCode = $theCode;
			switch ($theCode) {
				case 'TIPFORM':
					$content = $this->tipform();
					break;
				case 'TIPLINK':
					$content = $this->tiplink();
					break;
				default:
					$langKey = strtoupper($GLOBALS['TSFE']->config['config']['language']);
					$helpTemplate = $this->cObj->fileResource('EXT:tipafriend_plus/pi1/tipafriend_plus_help.tmpl');

					// Get language version
					$helpTemplate_lang = '';
					if ($langKey) {
						$helpTemplate_lang = $this->cObj->getSubpart($helpTemplate, '###TEMPLATE_' . $langKey . '###');
					}
					$helpTemplate = $helpTemplate_lang ? $helpTemplate_lang : $this->cObj->getSubpart($helpTemplate, '###TEMPLATE_DEFAULT###');

					// Markers and substitution:
					$markerArray['###CODE###'] = $this->theCode;
					$content .= $this->cObj->substituteMarkerArray($helpTemplate, $markerArray);
					break;
			}
		}

		return $content;
	}

	/**
	 * Checks the tipUrl and returns the form.
	 *
	 * @return string
	 */
	protected function tipform() {
		$content = '';

		$tipUrl = t3lib_div::_GP('tipUrl');
		$tipHash = (string) t3lib_div::_GP('tipHash');
		$calculatedHmac = t3lib_div::hmac($tipUrl, $this->hmacSalt);

		if ($tipHash !== $calculatedHmac) {
			// Show 404 error
			$GLOBALS['TSFE']->pageNotFoundAndExit($this->pi_getLL('no_valid_url'));
		} else {
			$GLOBALS['TSFE']->set_no_cache();

			$tipData = t3lib_div::_GP('TIPFORM');
			$tipData['recipient'] = $this->getRecipients($tipData['recipient']);
			list($tipData['email']) = explode(',', $this->getRecipients($tipData['email']));
			$url = strip_tags($tipUrl);

			// Preparing markers
			$wrappedSubpartArray = array();
			$subpartArray = array();

			$markerArray = array();
			$markerArray['###FORM_URL###'] = t3lib_div::getIndpEnv('REQUEST_URI');
			$markerArray['###URL###'] = $url;
			$markerArray['###URL_ENCODED###'] = rawurlencode($url);
			$markerArray['###URL_SPECIALCHARS###'] = htmlspecialchars($url);
			$markerArray['###URL_DISPLAY###'] = htmlspecialchars(strlen($url) > 70 ? t3lib_div::fixed_lgd_cs($url, 30) . t3lib_div::fixed_lgd_cs($url, -30) : $url);

			$markerArray['###HASH###'] = t3lib_div::hmac($url, $this->hmacSalt);
			$markerArray['###HASH_ENCODED###'] = t3lib_div::hmac(rawurlencode($url), $this->hmacSalt);
			// Because htmlspecialchared urls are resolved correctly (browsers convert the link themselves) we just need the normal hash
			$markerArray['###HASH_SPECIALCHARS###'] = t3lib_div::hmac($url, $this->hmacSalt);

			$markerArray['###TAF_LABEL_ERROR###'] = $this->pi_getLL('error');
			$markerArray['###TAF_ERROR_EXPL###'] = $this->pi_getLL('error_expl');
			$markerArray['###TAF_LABEL_NAME###'] = $this->pi_getLL('name');
			$markerArray['###TAF_LABEL_EMAIL###'] = $this->pi_getLL('email');
			$markerArray['###TAF_TITLE###'] = $this->pi_getLL('title');
			$markerArray['###TAF_LABEL_PATH###'] = $this->pi_getLL('path');

			$markerArray['###TAF_LABEL_EMAIL_RECIPENT###'] = $this->pi_getLL('email_recipent');
			$markerArray['###TAF_LABEL_MESSAGE###'] = $this->pi_getLL('message');
			$markerArray['###TAF_LABEL_HTML###'] = $this->pi_getLL('html');

			if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'checkbox_html', 'sDEF')) {
				$subpartArray['###HTML_INSERT###'] = '';
			}
			$markerArray['###TAF_LABEL_MUST###'] = $this->pi_getLL('must');
			$markerArray['###TAF_LABEL_SEND###'] = $this->pi_getLL('send');

			$disclaimer = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'disclaimer_text', 'sDEF');
			if (empty($disclaimer)) {
				$disclaimer = $this->pi_getLL('disclaimer');
			}
			$markerArray['###TAF_DISCLAIMER###'] = $disclaimer;
			$markerArray['###TAF_CONFIRMATION###'] = $this->pi_getLL('confirmation');
			$markerArray['###TAF_LABEL_BACK###'] = $this->pi_getLL('back');

			$markerArray['###FORM_CAPTCHA_RESPONSE###'] = $this->pi_getLL('form_captcha_response');

			$wrappedSubpartArray['###LINK###'] = array('<a href="' . htmlspecialchars($url) . '">', '</a>');

			// code inserted to use free Captcha
			if (is_object($this->freeCap)) {
				$markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
			} else {
				$subpartArray['###CAPTCHA_INSERT###'] = '';
			}
			// code inserted to use free Captcha

			// validation
			$error = 0;
			$sent = 0;
			if (t3lib_div::_GP('sendTip')) {

				if ($this->validate($tipData, $url)) {
					$this->sendTip($tipData, $url);
					$sent = 1;
				} else {
					$error = 1;
				}
			}
			// Display form
			if ($sent) {
				$subpart = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_TIPFORM_SENT###');

				$markerArray['###RECIPIENT###'] = htmlspecialchars($tipData['recipient']);

				$content = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray, $wrappedSubpartArray);
			} else {

				$captchaHTMLoutput = t3lib_extMgm::isLoaded('captcha') ? '<img src="' . t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php" alt="" />' : '';

				// Generate Captcha data and store string in session:

				$subpart = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_TIPFORM###');

				$markerArray['###MESSAGE###'] = htmlspecialchars($tipData['message']);
				$markerArray['###RECIPIENT###'] = htmlspecialchars($tipData['recipient']);

				// Pre-fill form data if FE user in logged in
				if ($GLOBALS['TSFE']->loginUser) {
					$markerArray['###YOUR_EMAIL###'] = $GLOBALS['TSFE']->fe_user->user['email'];
					$markerArray['###YOUR_NAME###'] = $GLOBALS['TSFE']->fe_user->user['name'];
				} else {
					$markerArray['###YOUR_EMAIL###'] = htmlspecialchars($tipData['email']);
					$markerArray['###YOUR_NAME###'] = htmlspecialchars($tipData['name']);
				}

				$markerArray['###HTML_MESSAGE###'] = $tipData['html_message'] ? 'checked' : '';
				$markerArray['###CAPTCHA_HTML###'] = $captchaHTMLoutput;

				if (!$error) {
					$subpartArray['###ERROR_MSG###'] = '';
				}

				// Substitute
				$content = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray, $wrappedSubpartArray);
			}
		}

		return $content;
	}

	/**
	 * Validates the submitted data.
	 *
	 * @param array $tipData
	 * @param string $url
	 * @return boolean
	 */
	protected function validate($tipData, $url) {

		// Remove any tags from url
		$url = strip_tags($url);

		// If the URL contains a '"', unset $url (suspecting XSS code)
		if (strstr($url, '"')) {
			$url = FALSE;
		}
		// Check if the host of the url is equal with current used one
		$urlParts = parse_url($url);
		if (empty($urlParts['host'])) {
			$url = FALSE;
		} elseif ($urlParts['host'] !== t3lib_div::getIndpEnv('TYPO3_HOST_ONLY')) {
			// Compare with registered domains
			$pidList = array(0);
			foreach ($GLOBALS['TSFE']->rootLine as $item) {
				$pidList[] = $item['uid'];
			}
			unset($item);

			$count = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
				'*',
				'sys_domain',
				'domainName=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($urlParts['host'], 'sys_domain') .
					' AND pid IN (' . implode(',', $pidList) . ') AND hidden=0'
			);
			if (!$count) {
				$url = FALSE;
			}
		}

		$ret = TRUE;
		if (trim($tipData['name'])) {
			if (preg_match('/[\r\n\f\e]/', $tipData['name']) > 0) {
				// Stop if there is a newline, carriage return, ...
				$tipData['name'] = '';
				$ret = FALSE;
			} else {
				// Search for characters that don't belong to one of the classes decimal, whitespace or word
				$pattern = '/[^\d\s\w]/';
				// Strip the mentioned characters
				$tipData['name'] = trim(preg_replace($pattern, '', $tipData['name']));
			}
		}

		if (
			$url &&
			$ret &&
			trim($tipData['name']) &&
			$tipData['email'] &&
			$tipData['recipient'] &&
			(!is_object($this->freeCap) || $this->freeCap->checkWord($tipData['captcha_response']))

		) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Returns only one receiver to avoid spam mails.
	 *
	 * @param string $emails
	 * @return string
	 */
	protected function getRecipients($emails) {
		// Prevent sending this recommendation to multiple recipients
		$emailArr = preg_split('/[,; ]/', $emails);

		return $emailArr[0];
	}

	/**
	 * Sends the email with submitted data to the receiver.
	 *
	 * @param array $tipData
	 * @param string $url
	 * @return void
	 */
	protected function sendTip($tipData, $url) {
		// Get template
		$subpart = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_EMAIL###');

		$markerArray = array();

		// Set markers
		$markerArray['###MESSAGE###'] = htmlspecialchars($tipData['message']);
		$markerArray['###RECIPIENT###'] = htmlspecialchars($tipData['recipient']);
		$markerArray['###YOUR_EMAIL###'] = htmlspecialchars($tipData['email']);
		$markerArray['###YOUR_NAME###'] = htmlspecialchars($tipData['name']);
		$markerArray['###URL###'] = $url;

		$subject = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'email_subject', 'sEmail');
		if (empty($subject)) {
			$subject = $this->pi_getLL('mail_subject');
		}
		$markerArray['###TAF_MAIL_SUBJECT###'] = $subject;
		$link_text = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'email_link_text', 'sEmail');
		if (empty($link_text)) {
			$link_text = $this->pi_getLL('mail_link');
		}
		$markerArray['###TAF_MAIL_LINK###'] = $link_text;
		$message = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'email_message_text', 'sEmail');
		if (empty($message)) {
			$message = $this->pi_getLL('mail_message');
		}
		$markerArray['###TAF_MAIL_MESSAGE###'] = $message;
		$footer = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'email_footer_text', 'sEmail');
		if (empty($footer)) {
			$footer = $this->pi_getLL('mail_footer');
		}
		$markerArray['###TAF_MAIL_FOOTER###'] = $footer;

		// Substitute in template
		$content = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray);

		// If htmlmail lib is included, then generate a nice HTML-email
		if ($this->conf['htmlmail'] || $tipData['html_message']) {
			list($subject, $plainMessage) = array_map('trim', explode(chr(10), trim($content), 2));
			$searchArray = array(
				$tipData['email'],
				$url
			);
			$replaceArray = array(
				'<a href="mailto:' . $tipData['email'] . '">' . $tipData['email'] . '</a>',
				'<a href="' . $url . '">' . $url . '</a>'
			);
			$htmlMessage = nl2br(str_replace($searchArray, $replaceArray, $plainMessage));
			$htmlMail = t3lib_div::makeInstance('t3lib_mail_Message');
			$htmlMail->setTo($tipData['recipient'])
				->setFrom($tipData['email'], $tipData['name'])
				->setReplyTo($tipData['email'], $tipData['name'])
				->setReturnPath($tipData['email'])
				->setPriority(3)
				->setSubject($subject)
				->addPart($htmlMessage, 'text/html')
				->addPart($plainMessage, 'text/plain')
				->send();

		} else { // Plain mail:
			// Sending mail:
			$plainMessage = trim($content);
			$this->cObj->sendNotifyEmail($plainMessage, $tipData['recipient'], '', $tipData['email'], $tipData['name']);
		}
	}

	/**
	 * Generates the tipUrl link for the configuration.
	 *
	 * @return string
	 */
	protected function tiplink() {
		$url = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
		$subpart = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_TIPLINK###');

		// Generate link configuration
		$tConf = $this->typolink_conf;
		$tConf['additionalParams'] .= '&tipUrl=' . rawurlencode($url) . '&tipHash=' . t3lib_div::hmac($url, $this->hmacSalt);

		if (empty($subpart)) {
			// Support native link output for easier update
			if (!empty($this->conf['value'])) {
				$value = $this->cObj->stdWrap($this->conf['value'], $this->conf['value.']);
			} else {
				$value = $this->pi_getLL('link');
			}

			return $this->cObj->typoLink($value, $tConf);
		} else {
			// Generate markerArray for template substitution
			$wrappedSubpartArray = array();
			$wrappedSubpartArray['###LINK###'] = $this->cObj->typolinkWrap($tConf);

			$markerArray = array();
			$markerArray['###URL###'] = $url;
			$markerArray['###URL_ENCODED###'] = rawurlencode($url);
			$markerArray['###URL_SPECIALCHARS###'] = htmlspecialchars($url);

			$markerArray['###TAF_LINK###'] = $this->pi_getLL('link');

			// Substitute
			$content = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, array(), $wrappedSubpartArray);

			return $content;
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/tipafriend_plus/pi1/class.tx_tipafriendplus_pi1.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/tipafriend_plus/pi1/class.tx_tipafriendplus_pi1.php']);
}

?>