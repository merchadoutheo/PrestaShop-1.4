<?php
/*
* 2007-2010 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Prestashop SA <contact@prestashop.com>
*  @copyright  2007-2010 Prestashop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*/

class ToolsCore
{
	protected static $file_exists_cache = array();
	protected static $_forceCompile;
	protected static $_caching;
	
	/**
	* Random password generator
	*
	* @param integer $length Desired length (optional)
	* @return string Password
	*/
	static public function passwdGen($length = 8)
	{
		$str = 'abcdefghijkmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		for ($i = 0, $passwd = ''; $i < $length; $i++)
			$passwd .= self::substr($str, mt_rand(0, self::strlen($str) - 1), 1);
		return $passwd;
	}

	/**
	* Redirect user to another page
	*
	* @param string $url Desired URL
	* @param string $baseUri Base URI (optional)
	*/
	static public function redirect($url, $baseUri = __PS_BASE_URI__)
	{
		if (isset($_SERVER['HTTP_REFERER']) AND ($url == $_SERVER['HTTP_REFERER']))
			header('Location: '.$_SERVER['HTTP_REFERER']);
		else
			header('Location: '.$baseUri.$url);
		exit;
	}

	/**
	* Redirect url wich allready PS_BASE_URI
	*
	* @param string $url Desired URL
	*/
	static public function redirectLink($url)
	{
		header('Location: '.$url);
		exit;
	}

	/**
	* Redirect user to another admin page
	*
	* @param string $url Desired URL
	*/
	static public function redirectAdmin($url)
	{
		header('Location: '.$url);
		exit;
	}

	static public function getHttpHost($http = false, $entities = false)
	{
		$host = (isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST']);
		if ($entities)
			$host = htmlspecialchars($host, ENT_COMPAT, 'UTF-8');
		if ($http)
			$host = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').$host;
		return $host;
	}

	/**
	* Get the server variable SERVER_NAME
	*
	* @param string $referrer URL referrer
	*/
	static function getServerName()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_SERVER']) AND $_SERVER['HTTP_X_FORWARDED_SERVER'])
			return $_SERVER['HTTP_X_FORWARDED_SERVER'];
		return $_SERVER['SERVER_NAME'];
	}

	/**
	* Get the server variable REMOTE_ADDR
	*
	* @param string $remote_addr ip of client
	*/
	static function getRemoteAddr()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) AND $_SERVER['HTTP_X_FORWARDED_FOR'])
		{
			if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ','))
			{
				$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
				return $ips[0];
			}
			else
				return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	* Secure an URL referrer
	*
	* @param string $referrer URL referrer
	*/
	static public function secureReferrer($referrer)
	{
		if (preg_match('/^http[s]?:\/\/'.self::getServerName().'(:'._PS_SSL_PORT_.')?\/.*$/Ui', $referrer))
			return $referrer;
		return __PS_BASE_URI__;
	}

	/**
	* Get a value from $_POST / $_GET
	* if unavailable, take a default value
	*
	* @param string $key Value key
	* @param mixed $defaultValue (optional)
	* @return mixed Value
	*/
	static public function getValue($key, $defaultValue = false)
	{
	 	if (!isset($key) OR empty($key) OR !is_string($key))
			return false;
		$ret = (isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : $defaultValue));

		if (is_string($ret) === true)
			$ret = urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($ret)));
		return !is_string($ret)? $ret : stripslashes($ret);
	}

	static public function getIsset($key)
	{
	 	if (!isset($key) OR empty($key) OR !is_string($key))
			return false;
	 	return isset($_POST[$key]) ? true : (isset($_GET[$key]) ? true : false);
	}

	/**
	* Change language in cookie while clicking on a flag
	*/
	static public function setCookieLanguage()
	{
		global $cookie;

		/* If language does not exist or is disabled, erase it */
		if ($cookie->id_lang)
		{
			$lang = new Language((int)($cookie->id_lang));
			if (!Validate::isLoadedObject($lang) OR !$lang->active)
				$cookie->id_lang = NULL;
		}

		/* Automatically detect language if not already defined */
		if (!$cookie->id_lang AND isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			$array = explode(',', self::strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));
			if (self::strlen($array[0]) > 2)
			{
				$tab = explode('-', $array[0]);
				$string = $tab[0];
			}
			else
				$string = $array[0];
			if (Validate::isLanguageIsoCode($string))
			{
				$lang = new Language((int)(Language::getIdByIso($string)));
				if (Validate::isLoadedObject($lang) AND $lang->active)
					$cookie->id_lang = (int)($lang->id);
			}
		}

		/* If language file not present, you must use default language file */
		if (!$cookie->id_lang OR !Validate::isUnsignedId($cookie->id_lang))
			$cookie->id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));

		$iso = Language::getIsoById((int)($cookie->id_lang));
		@include_once(_PS_TRANSLATIONS_DIR_.$iso.'/fields.php');
		@include_once(_PS_TRANSLATIONS_DIR_.$iso.'/errors.php');
		@include_once(_PS_THEME_DIR_.'lang/'.$iso.'.php');
		return $iso;
	}

	static public function switchLanguage()
	{
		global $cookie;

		if ($id_lang = (int)(self::getValue('id_lang')) AND Validate::isUnsignedId($id_lang))
			$cookie->id_lang = $id_lang;
	}

	static public function setCurrency()
	{
		global $cookie;

		if (self::isSubmit('SubmitCurrency'))
			if (isset($_POST['id_currency']) AND is_numeric($_POST['id_currency']))
			{
				$currency = Currency::getCurrencyInstance((int)($_POST['id_currency']));
				if (is_object($currency) AND $currency->id AND !$currency->deleted)
					$cookie->id_currency = (int)($currency->id);
			}

		if ($cookie->id_currency)
		{
			$currency = Currency::getCurrencyInstance((int)($cookie->id_currency));
			if (is_object($currency) AND $currency->id AND (int)($currency->deleted) != 1)
				return $currency;
		}
		$currency = Currency::getCurrencyInstance((int)(Configuration::get('PS_CURRENCY_DEFAULT')));
		if (is_object($currency) AND $currency->id)
			$cookie->id_currency = (int)($currency->id);
		return $currency;
	}

	/**
	* Return price with currency sign for a given product
	*
	* @param float $price Product price
	* @param object $currency Current currency (object, id_currency, NULL => getCurrent())
	* @return string Price correctly formated (sign, decimal separator...)
	*/
	static public function displayPrice($price, $currency = NULL, $no_utf8 = false)
	{
		if ($currency === NULL)
			$currency = Currency::getCurrent();
		/* if you modified this function, don't forget to modify the Javascript function formatCurrency (in tools.js) */
		if (is_int($currency))
			$currency = Currency::getCurrencyInstance((int)($currency));
		$c_char = (is_array($currency) ? $currency['sign'] : $currency->sign);
		$c_format = (is_array($currency) ? $currency['format'] : $currency->format);
		$c_decimals = (is_array($currency) ? (int)($currency['decimals']) : (int)($currency->decimals)) * _PS_PRICE_DISPLAY_PRECISION_;
		$c_blank = (is_array($currency) ? $currency['blank'] : $currency->blank);
		$blank = ($c_blank ? ' ' : '');
		$ret = 0;
		if (($isNegative = ($price < 0)))
			$price *= -1;
		$price = self::ps_round($price, $c_decimals);
		switch ($c_format)
	 	{
	 	 	/* X 0,000.00 */
	 	 	case 1:
				$ret = $c_char.$blank.number_format($price, $c_decimals, '.', ',');
				break;
			/* 0 000,00 X*/
			case 2:
				$ret = number_format($price, $c_decimals, ',', ' ').$blank.$c_char;
				break;
			/* X 0.000,00 */
			case 3:
				$ret = $c_char.$blank.number_format($price, $c_decimals, ',', '.');
				break;
			/* 0,000.00 X */
			case 4:
				$ret = number_format($price, $c_decimals, '.', ',').$blank.$c_char;
				break;
		}
		if ($isNegative)
			$ret = '-'.$ret;
		if ($no_utf8)
			return str_replace('€', chr(128), $ret);
		return $ret;
	}

	static public function displayPriceSmarty($params, &$smarty)
	{
		if (array_key_exists('currency', $params))
		{
			$currency = Currency::getCurrencyInstance((int)($params['currency']));
			if (Validate::isLoadedObject($currency))
				return self::displayPrice($params['price'], $currency, false);
		}
		return self::displayPrice($params['price']);
	}

	/**
	* Return price converted
	*
	* @param float $price Product price
	* @param object $currency Current currency object
	* @param boolean $to_currency convert to currency or from currency to default currency
	*/
	static public function convertPrice($price, $currency = NULL, $to_currency = true)
	{
		if ($currency === NULL)
			$currency = Currency::getCurrent();
		elseif (is_numeric($currency))
			$currency = Currency::getCurrencyInstance($currency);
			
		$c_id = (is_array($currency) ? $currency['id_currency'] : $currency->id);
		$c_rate = (is_array($currency) ? $currency['conversion_rate'] : $currency->conversion_rate);
		
		if ($c_id != (int)(Configuration::get('PS_CURRENCY_DEFAULT')))
		{
			if ($to_currency)
				$price *= $c_rate;
			else 
				$price /= $c_rate;
		}
		
		return $price;
	}
	
	

	/**
	* Display date regarding to language preferences
	*
	* @param array $params Date, format...
	* @param object $smarty Smarty object for language preferences
	* @return string Date
	*/
	static public function dateFormat($params, &$smarty)
	{
		return self::displayDate($params['date'], $smarty->ps_language->id, (isset($params['full']) ? $params['full'] : false));
	}

	/**
	* Display date regarding to language preferences
	*
	* @param string $date Date to display format UNIX
	* @param integer $id_lang Language id
	* @param boolean $full With time or not (optional)
	* @return string Date
	*/
	static public function displayDate($date, $id_lang, $full = false, $separator='-')
	{
	 	if (!$date OR !strtotime($date))
	 		return $date;
		if (!Validate::isDate($date) OR !Validate::isBool($full))
			die (self::displayError('Invalid date'));
	 	$tmpTab = explode($separator, substr($date, 0, 10));
	 	$hour = ' '.substr($date, -8);

		$language = Language::getLanguage((int)($id_lang));
	 	if ($language AND strtolower($language['iso_code']) == 'fr')
	 		return ($tmpTab[2].'-'.$tmpTab[1].'-'.$tmpTab[0].($full ? $hour : ''));
	 	else
	 		return ($tmpTab[0].'-'.$tmpTab[1].'-'.$tmpTab[2].($full ? $hour : ''));
	}

	/**
	* Sanitize a string
	*
	* @param string $string String to sanitize
	* @param boolean $full String contains HTML or not (optional)
	* @return string Sanitized string
	*/
	static public function safeOutput($string, $html = false)
	{
	 	if (!$html)
			$string = @htmlentities(strip_tags($string), ENT_QUOTES, 'utf-8');
		return $string;
	}

	static public function htmlentitiesUTF8($string, $type = ENT_QUOTES)
	{
		if (is_array($string))
			return array_map(array('Tools', 'htmlentitiesUTF8'), $string);
		return htmlentities($string, $type, 'utf-8');
	}

	static public function htmlentitiesDecodeUTF8($string)
	{
		if (is_array($string))
			return array_map(array('Tools', 'htmlentitiesDecodeUTF8'), $string);
		return html_entity_decode($string, ENT_QUOTES, 'utf-8');
	}

	static public function safePostVars()
	{
		$_POST = array_map(array('Tools', 'htmlentitiesUTF8'), $_POST);
	}

	/**
	* Delete directory and subdirectories
	*
	* @param string $dirname Directory name
	*/
	static public function deleteDirectory($dirname)
	{
		$files = scandir($dirname);
		foreach ($files as $file)
			if ($file != '.' AND $file != '..')
			{
				if (is_dir($file))
					self::deleteDirectory($file);
				elseif (file_exists($file))
					unlink($file);
				else
					echo 'Unable to delete '.$file;
			}
		rmdir($dirname);
	}

	/**
	* Display an error according to an error code
	*
	* @param integer $code Error code
	*/
	static public function displayError($string = 'Fatal error', $htmlentities = true)
	{
		global $_ERRORS;

		//if ($string == 'Fatal error') d(debug_backtrace());
		if (!is_array($_ERRORS))
			return str_replace('"', '&quot;', $string);
		$key = md5(str_replace('\'', '\\\'', $string));
		$str = (isset($_ERRORS) AND is_array($_ERRORS) AND key_exists($key, $_ERRORS)) ? ($htmlentities ? htmlentities($_ERRORS[$key], ENT_COMPAT, 'UTF-8') : $_ERRORS[$key]) : $string;
		return str_replace('"', '&quot;', stripslashes($str));
	}

	/**
	* Display an error with detailed object
	*
	* @param object $object Object to display
	*/
	static public function dieObject($object, $kill = true)
	{
		echo '<pre style="text-align: left;">';
		print_r($object);
		echo '</pre><br />';
		if ($kill)
			die('END');
		return ($object);
	}

	/**
	* ALIAS OF dieObject() - Display an error with detailed object
	*
	* @param object $object Object to display
	*/
	static public function d($object, $kill = true)
	{
		return (self::dieObject($object, $kill = true));
	}

	/**
	* ALIAS OF dieObject() - Display an error with detailed object but don't stop the execution
	*
	* @param object $object Object to display
	*/
	static public function p($object)
	{
		return (self::dieObject($object, false));
	}

	/**
	* Check if submit has been posted
	*
	* @param string $submit submit name
	*/
	static public function isSubmit($submit)
	{
		return (
			isset($_POST[$submit]) OR isset($_POST[$submit.'_x']) OR isset($_POST[$submit.'_y'])
			OR isset($_GET[$submit]) OR isset($_GET[$submit.'_x']) OR isset($_GET[$submit.'_y'])
		);
	}

	/**
	* Get meta tages for a given page
	*
	* @param integer $id_lang Language id
	* @return array Meta tags
	*/
	static public function getMetaTags($id_lang)
	{
		global $maintenance;

		if (!(isset($maintenance) AND (!in_array(Tools::getRemoteAddr(), explode(',', Configuration::get('PS_MAINTENANCE_IP'))))))
		{
		 	/* Products specifics meta tags */
			if ($id_product = self::getValue('id_product'))
			{
				$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
				SELECT `name`, `meta_title`, `meta_description`, `meta_keywords`, `description_short`
				FROM `'._DB_PREFIX_.'product` p 
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.`id_product` = p.`id_product`) 
				WHERE pl.id_lang = '.(int)($id_lang).' AND pl.id_product = '.(int)($id_product).' AND p.active = 1');
				if ($row)
				{
					if (empty($row['meta_description']))
						$row['meta_description'] = strip_tags($row['description_short']);
					return self::completeMetaTags($row, $row['name']);
				}
			}

			/* Categories specifics meta tags */
			elseif ($id_category = self::getValue('id_category'))
			{
				$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
				SELECT `name`, `meta_title`, `meta_description`, `meta_keywords`, `description`
				FROM `'._DB_PREFIX_.'category_lang`
				WHERE id_lang = '.(int)($id_lang).' AND id_category = '.(int)($id_category));
				if ($row)
				{
					if (empty($row['meta_description']))
						$row['meta_description'] = strip_tags($row['description']);
					return self::completeMetaTags($row, $row['name']);
				}
			}

			/* Manufacturers specifics meta tags */
			elseif ($id_manufacturer = self::getValue('id_manufacturer'))
			{
				$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
				SELECT `meta_title`, `meta_description`, `meta_keywords`
				FROM `'._DB_PREFIX_.'manufacturer_lang`
				WHERE id_lang = '.(int)($id_lang).' AND id_manufacturer = '.(int)($id_manufacturer));
				if ($row)
				{
					if (empty($row['meta_description']))
						$row['meta_description'] = strip_tags($row['meta_description']);
					$row['meta_title'] = $row['meta_title'].' - '.Configuration::get('PS_SHOP_NAME');
					return self::completeMetaTags($row, $row['meta_title']);
				}
			}

			/* Suppliers specifics meta tags */
			elseif ($id_supplier = self::getValue('id_supplier'))
			{
				$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
				SELECT `meta_title`, `meta_description`, `meta_keywords`
				FROM `'._DB_PREFIX_.'supplier_lang`
				WHERE id_lang = '.(int)($id_lang).' AND id_supplier = '.(int)($id_supplier));
				if ($row)
				{
					if (empty($row['meta_description']))
						$row['meta_description'] = strip_tags($row['meta_description']);
					$row['meta_title'] = $row['meta_title'].' - '.Configuration::get('PS_SHOP_NAME');
					return self::completeMetaTags($row, $row['meta_title']);
				}
			}

			/* CMS specifics meta tags */
			elseif ($id_cms = self::getValue('id_cms'))
			{
				$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
				SELECT `meta_title`, `meta_description`, `meta_keywords`
				FROM `'._DB_PREFIX_.'cms_lang`
				WHERE id_lang = '.(int)($id_lang).' AND id_cms = '.(int)($id_cms));
				if ($row)
				{
					$row['meta_title'] = $row['meta_title'].' - '.Configuration::get('PS_SHOP_NAME');
					return self::completeMetaTags($row, $row['meta_title']);
				}
			}
		}

		/* Default meta tags */
		return self::getHomeMetaTags($id_lang);
	}

	/**
	* Get meta tags for a given page
	*
	* @param integer $id_lang Language id
	* @return array Meta tags
	*/
	static public function getHomeMetaTags($id_lang)
	{
		global $cookie, $page_name;

		/* Metas-tags */
		$metas = Meta::getMetaByPage($page_name, $id_lang);
		$ret['meta_title'] = (isset($metas['title']) AND $metas['title']) ? $metas['title'].' - '.Configuration::get('PS_SHOP_NAME') : Configuration::get('PS_SHOP_NAME');
		$ret['meta_description'] = (isset($metas['description']) AND $metas['description']) ? $metas['description'] : '';
		$ret['meta_keywords'] = (isset($metas['keywords']) AND $metas['keywords']) ? $metas['keywords'] :  '';
		return $ret;
	}


	static public function completeMetaTags($metaTags, $defaultValue)
	{
		global $cookie;

		if ($metaTags['meta_title'] == NULL)
			$metaTags['meta_title'] = $defaultValue.' - '.Configuration::get('PS_SHOP_NAME');
		if ($metaTags['meta_description'] == NULL)
			$metaTags['meta_description'] = Configuration::get('PS_META_DESCRIPTION', (int)($cookie->id_lang)) ? Configuration::get('PS_META_DESCRIPTION', (int)($cookie->id_lang)) : '';
		if ($metaTags['meta_keywords'] == NULL)
			$metaTags['meta_keywords'] = Configuration::get('PS_META_KEYWORDS', (int)($cookie->id_lang)) ? Configuration::get('PS_META_KEYWORDS', (int)($cookie->id_lang)) : '';
		return $metaTags;
	}

	/**
	* Encrypt password
	*
	* @param object $object Object to display
	*/
	static public function encrypt($passwd)
	{
		return md5(pSQL(_COOKIE_KEY_.$passwd));
	}

	/**
	* Get token to prevent CSRF
	*
	* @param string $token token to encrypt
	*/
	static public function getToken($page = true)
	{
		global $cookie;
		if ($page === true)
			return (self::encrypt($cookie->id_customer.$cookie->passwd.$_SERVER['SCRIPT_NAME']));
		else
			return (self::encrypt($cookie->id_customer.$cookie->passwd.$page));
	}

	/**
	* Encrypt password
	*
	* @param object $object Object to display
	*/
	static public function getAdminToken($string)
	{
		return !empty($string) ? self::encrypt($string) : false;
	}
	static public function getAdminTokenLite($tab)
	{
		global $cookie;
		return Tools::getAdminToken($tab.(int)(Tab::getIdFromClassName($tab)).(int)($cookie->id_employee));
	}

	/**
	* Get the user's journey
	*
	* @param integer category id
	* @param string finish of the path
	* @param string [optionnal] $type_cat defined what type of categories is used (products or cms)
	*/
	static public function getPath($id_category, $path = '', $linkOntheLastItem = false, $type_cat = 'products')
	{
		global $link, $cookie;
		
		if($type_cat === 'products')
		    $category = new Category((int)($id_category), (int)($cookie->id_lang));
		else if ($type_cat === 'CMS')
		    $category = new CMSCategory((int)($id_category), (int)($cookie->id_lang));
		
		if (!Validate::isLoadedObject($category))
			die (self::displayError());
		if ($category->id == 1)
			return '<span class="navigation_end">'.$path.'</span>';
		$pipe = (Configuration::get('PS_NAVIGATION_PIPE') ? Configuration::get('PS_NAVIGATION_PIPE') : '>');
		$category_name = $category->name;
		$category_link = $type_cat === 'products' ? $link->getCategoryLink($category) : $link->getCMSCategoryLink($category);
		// htmlentitiezed because this method generates some view
		if ($path != $category_name)
		{
			$displayedPath = '';
			if ($category->active)
				$displayedPath .= '<a href="'.self::safeOutput($category_link).'">';
			$displayedPath .= htmlentities($category_name, ENT_NOQUOTES, 'UTF-8');
			if ($category->active)
				$displayedPath .= '</a>';
			$displayedPath .= '<span class="navigation-pipe">'.$pipe.'</span>'.$path;
		}
		else
			$displayedPath = ($linkOntheLastItem ? '<a href="'.self::safeOutput($category_link).'">' : '').htmlentities($path, ENT_NOQUOTES, 'UTF-8').($linkOntheLastItem ? '</a>' : '');
		return self::getPath((int)($category->id_parent), $displayedPath, $linkOntheLastItem, $type_cat);
	}
	/**
     * @param string [optionnal] $type_cat defined what type of categories is used (products or cms)
	 */
	static public function getFullPath($id_category, $end, $type_cat = 'products')
	{
		global $cookie;

		$pipe = (Configuration::get('PS_NAVIGATION_PIPE') ? Configuration::get('PS_NAVIGATION_PIPE') : '>');
		
		if($type_cat === 'products')
		    $category = new Category((int)($id_category), (int)($cookie->id_lang));
		else if ($type_cat === 'CMS')
		    $category = new CMSCategory((int)($id_category), (int)($cookie->id_lang));
		    
		if (!Validate::isLoadedObject($category))
			die(self::displayError());
		if ($id_category == 1)
			return htmlentities($end, ENT_NOQUOTES, 'UTF-8');
		return self::getPath($id_category, $category->name, true, $type_cat).'<span class="navigation-pipe">'.$pipe.'</span> <span class="navigation_product">'.htmlentities($end, ENT_NOQUOTES, 'UTF-8').'</span>';
	}

	/**
	* Stats for admin panel
	*
	* @return integer Categories total
	*/

	static public function getCategoriesTotal()
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT COUNT(`id_category`) AS total FROM `'._DB_PREFIX_.'category`');
		return (int)($row['total']);
	}

	/**
	* Stats for admin panel
	*
	* @return integer Products total
	*/

	static public function getProductsTotal()
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT COUNT(`id_product`) AS total FROM `'._DB_PREFIX_.'product`');
		return (int)($row['total']);
	}

	/**
	* Stats for admin panel
	*
	* @return integer Customers total
	*/

	static public function getCustomersTotal()
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT COUNT(`id_customer`) AS total FROM `'._DB_PREFIX_.'customer`');
		return (int)($row['total']);
	}

	/**
	* Stats for admin panel
	*
	* @return integer Orders total
	*/

	static public function getOrdersTotal()
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT COUNT(`id_order`) AS total FROM `'._DB_PREFIX_.'orders`');
		return (int)($row['total']);
	}

	/*
	** Historyc translation function kept for compatibility
	** Removing soon
	*/
	static public function historyc_l($key, $translations)
	{
		global $cookie;
		if (!$translations OR !is_array($translations))
			die(self::displayError());
		$iso = strtoupper(Language::getIsoById($cookie->id_lang));
		$lang = key_exists($iso, $translations) ? $translations[$iso] : false;
		return (($lang AND is_array($lang) AND key_exists($key, $lang)) ? stripslashes($lang[$key]) : $key);
	}

	static public function link_rewrite($str, $utf8_decode = false)
	{
		$purified = '';
		$length = self::strlen($str);
		if ($utf8_decode)
			$str = utf8_decode($str);
		for ($i = 0; $i < $length; $i++)
		{
			$char = self::substr($str, $i, 1);
			if (self::strlen(htmlentities($char)) > 1)
			{
				$entity = htmlentities($char, ENT_COMPAT, 'UTF-8');
				$purified .= $entity{1};
			}
			elseif (preg_match('|[[:alpha:]]{1}|u', $char))
				$purified .= $char;
			elseif (preg_match('<[[:digit:]]|-{1}>', $char))
				$purified .= $char;
			elseif ($char == ' ')
				$purified .= '-';
			elseif ($char == '\'')
				$purified .= '-';
		}
		return trim(self::strtolower($purified));
	}

	/**
	* Truncate strings
	*
	* @param string $str
	* @param integer $maxLen Max length
	* @param string $suffix Suffix optional
	* @return string $str truncated
	*/
	/* CAUTION : Use it only on module hookEvents.
	** For other purposes use the smarty function instead */
	static public function truncate($str, $maxLen, $suffix = '...')
	{
	 	if (self::strlen($str) <= $maxLen)
	 		return $str;
	 	$str = utf8_decode($str);
	 	return (utf8_encode(substr($str, 0, $maxLen - self::strlen($suffix)).$suffix));
	}

	/**
	* Generate date form
	*
	* @param integer $year Year to select
	* @param integer $month Month to select
	* @param integer $day Day to select
	* @return array $tab html data with 3 cells :['days'], ['months'], ['years']
	*
	*/
	static public function dateYears()
	{
		for ($i = date('Y') - 10; $i >= 1900; $i--)
			$tab[] = $i;
		return $tab;
	}

	static public function dateDays()
	{
		for ($i = 1; $i != 32; $i++)
			$tab[] = $i;
		return $tab;
	}

	static public function dateMonths()
	{
		for ($i = 1; $i != 13; $i++)
			$tab[$i] = date('F', mktime(0, 0, 0, $i, date('m'), date('Y')));
		return $tab;
	}

	static public function hourGenerate($hours, $minutes, $seconds)
	{
	    return implode(':', array($hours, $minutes, $seconds));
	}

	static public function dateFrom($date)
	{
		$tab = explode(' ', $date);
		if (!isset($tab[1]))
		    $date .= ' ' . self::hourGenerate(0, 0, 0);
		return $date;
	}

	static public function dateTo($date)
	{
		$tab = explode(' ', $date);
		if (!isset($tab[1]))
		    $date .= ' ' . self::hourGenerate(23, 59, 59);
		return $date;
	}

	static public function getExactTime()
	{
		return time()+microtime();
	}

	static function strtolower($str)
	{
		if (is_array($str))
			return false;
		if (function_exists('mb_strtolower'))
			return mb_strtolower($str, 'utf-8');
		return strtolower($str);
	}

	static function strlen($str)
	{
		if (is_array($str))
			return false;
		$str = html_entity_decode($str, ENT_COMPAT, 'UTF-8');
		if (function_exists('mb_strlen'))
			return mb_strlen($str, 'utf-8');
		return strlen($str);
	}

	static function stripslashes($string)
	{
		if (_PS_MAGIC_QUOTES_GPC_)
			$string = stripslashes($string);
		return $string;
	}

	static function strtoupper($str)
	{
		if (is_array($str))
			return false;
		if (function_exists('mb_strtoupper'))
			return mb_strtoupper($str, 'utf-8');
		return strtoupper($str);
	}

	static function substr($str, $start, $length = false, $encoding = 'utf-8')
	{
		if (is_array($str))
			return false;
		if (function_exists('mb_substr'))
			return mb_substr($str, (int)($start), ($length === false ? self::strlen($str) : (int)($length)), $encoding);
		return substr($str, $start, ($length === false ? strlen($str) : (int)($length)));
	}

	static function ucfirst($str)
	{
		return self::strtoupper(self::substr($str, 0, 1)).self::substr($str, 1);
	}

	static public function orderbyPrice(&$array, $orderWay)
	{
		foreach($array as &$row)
			$row['price_tmp'] =  Product::getPriceStatic($row['id_product'], true, ((isset($row['id_product_attribute']) AND !empty($row['id_product_attribute'])) ? (int)($row['id_product_attribute']) : NULL), 2);
		if(strtolower($orderWay) == 'desc')
			uasort($array, 'cmpPriceDesc');
		else
			uasort($array, 'cmpPriceAsc');
		foreach($array as &$row)
			unset($row['price_tmp']);
	}

	static public function iconv($from, $to, $string)
	{
		if (function_exists('iconv'))
			return iconv($from, $to.'//TRANSLIT', str_replace('¥', '&yen;', str_replace('£', '&pound;', str_replace('€', '&euro;', $string))));
		return html_entity_decode(htmlentities($string, ENT_NOQUOTES, $from), ENT_NOQUOTES, $to);
	}

	static public function isEmpty($field)
	{
		return $field === '' OR $field === NULL;
	}

	/**
	* DEPRECATED FUNCTION
	* DO NOT USE IT
	**/
	static public function getTimezones($select = false)
	{
		static $_cache = 0;

		// One select
		if ($select)
		{
			// No cache
			if (!$_cache)
			{
				$tmz = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT `name` FROM '._DB_PREFIX_.'timezone WHERE id_timezone = '.(int)($select));
				$_cache = $tmz['name'];
			}
			return $_cache;
		}

		// Multiple select
		$tmz = Db::getInstance(_PS_USE_SQL_SLAVE_)->s('SELECT * FROM '._DB_PREFIX_.'timezone');
		$tab = array();
		foreach ($tmz as $timezone)
			$tab[$timezone['id_timezone']] = str_replace('_', ' ', $timezone['name']);
		return $tab;
	}

	/**
	* DEPRECATED FUNCTION
	* DO NOT USE IT
	**/
	static public function ps_set_magic_quotes_runtime($var)
	{
		if (function_exists('set_magic_quotes_runtime'))
			@set_magic_quotes_runtime($var);
	}

	static public function ps_round($value, $precision = 0)
	{
		$method = (int)(Configuration::get('PS_PRICE_ROUND_MODE'));
		if ($method == PS_ROUND_UP)
			return Tools::ceilf($value, $precision);
		elseif ($method == PS_ROUND_DOWN)
			return Tools::floorf($value, $precision);
		return round($value, $precision);
	}

	static public function ceilf($value, $precision = 0)
	{
		$precisionFactor = $precision == 0 ? 1 : pow(10, $precision);
		$tmp = $value * $precisionFactor;
		$tmp2 = (string)$tmp;
		// If the current value has already the desired precision
		if (strpos($tmp2, '.') === false)
			return ($value);
		if ($tmp2[strlen($tmp2) - 1] == 0)
			return $value;
		return ceil($tmp) / $precisionFactor;
	}

	static public function floorf($value, $precision = 0)
	{
		$precisionFactor = $precision == 0 ? 1 : pow(10, $precision);
		$tmp = $value * $precisionFactor;
		$tmp2 = (string)$tmp;
		// If the current value has already the desired precision
		if (strpos($tmp2, '.') === false)
			return ($value);
		if ($tmp2[strlen($tmp2) - 1] == 0)
			return $value;
		return floor($tmp) / $precisionFactor;
	}
	
	/**
	 * file_exists() wrapper with cache to speedup performance
	 *
	 * @param string $filename File name
	 * @return boolean Cached result of file_exists($filename)
	 */
	static public function file_exists_cache($filename)
	{
		if (!isset(self::$file_exists_cache[$filename]))
			self::$file_exists_cache[$filename] = file_exists($filename);
		return self::$file_exists_cache[$filename];
	}
	
	static public function minifyHTML ($html_content)
	{
		if (strlen($html_content) > 0)
		{
			//set an alphabetical order for args
			$html_content = preg_replace_callback(
				'/(<[a-zA-Z0-9]+)((\s?[a-zA-Z0-9]+=[\"\\\'][^\"\\\']*[\"\\\']\s?)*)>/'
				,array('Tools', 'minifyHTMLpregCallback')
				,$html_content);
			
			require_once(_PS_TOOL_DIR_.'minify_html/minify_html.class.php');
			$html_content = Minify_HTML::minify($html_content, array('xhtml', 'cssMinifier', 'jsMinifier'));
			
			if (Configuration::get('PS_HIGH_HTML_THEME_COMPRESSION'))
			{
				//$html_content = preg_replace('/"([^\>\s"]*)"/i', '$1', $html_content);//FIXME create a js bug
				$html_content = preg_replace('/<!DOCTYPE \w[^\>]*dtd\">/is', '', $html_content);
				$html_content = preg_replace('/\s\>/is', '>', $html_content);
				$html_content = str_replace('</li>', '', $html_content);
				$html_content = str_replace('</dt>', '', $html_content);
				$html_content = str_replace('</dd>', '', $html_content);
				$html_content = str_replace('</head>', '', $html_content);
				$html_content = str_replace('<head>', '', $html_content);
				$html_content = str_replace('</html>', '', $html_content);
				$html_content = str_replace('</body>', '', $html_content);
				//$html_content = str_replace('</p>', '', $html_content);//FIXME doesnt work...
				$html_content = str_replace("</option>\n", '', $html_content);//TODO with bellow
				$html_content = str_replace('</option>', '', $html_content);
				$html_content = str_replace('<script type=text/javascript>', '<script>', $html_content);//Do a better expreg
				$html_content = str_replace("<script>\n", '<script>', $html_content);//Do a better expreg
			}

			return $html_content;
		}
		return false;
	}
	
	/**
	* Translates a string with underscores into camel case (e.g. first_name -> firstName)
	* @prototype string public static function toCamelCase(string $str[, bool $capitaliseFirstChar = false])
	*/
	public static function toCamelCase($str, $capitaliseFirstChar = false) {
		if($capitaliseFirstChar)
			$str[0] = strtoupper($str[0]);
		return preg_replace_callback('/_([a-z])/', create_function('$c', 'return strtoupper($c[1]);'), $str);
	}

	static public function getBrightness($hex)
	{
		$hex = str_replace('#', '', $hex);
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
		return (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
	}
	static public function minifyHTMLpregCallback ($preg_matches)
	{
		$args = array();
		preg_match_all('/[a-zA-Z0-9]+=[\"\\\'][^\"\\\']*[\"\\\']/is', $preg_matches[2], $args);
		$args = $args[0];
		sort($args);
		$output = $preg_matches[1].' '.implode(' ', $args).'>';
		return $output;
	}
	
	static public function packJSinHTML ($html_content)
	{
		if (strlen($html_content) > 0)
		{
			$html_content = preg_replace_callback(
				'/\\s*(<script\\b[^>]*?>)([\\s\\S]*?)(<\\/script>)\\s*/i'
				,array('Tools', 'packJSinHTMLpregCallback')
				,$html_content);
			return $html_content;
		}
		return false;
	}
	
	static public function packJSinHTMLpregCallback ($preg_matches)
	{
		$preg_matches[1] = $preg_matches[1].'/* <![CDATA[ */';
		$preg_matches[2] = self::packJS($preg_matches[2], 'None');
		$preg_matches[count($preg_matches)-1] = '/* ]]> */'.$preg_matches[count($preg_matches)-1];
		unset($preg_matches[0]);
		$output = implode('', $preg_matches);
		return $output;
	}
	
	
	static public function packJS ($js_content, $encoding = 62)
	{
		if (strlen($js_content) > 0)
		{
			require_once(_PS_TOOL_DIR_.'javascript_packer/class.JavaScriptPacker.php');
			$packer = new JavaScriptPacker($js_content, $encoding, true, true);
			return $packer->pack();
		}
		return false;
	}
	
	static public function minifyCSS ($css_content, $fileuri = false)
	{
		global $current_css_file;
		$current_css_file = $fileuri;
		
		if (strlen($css_content) > 0)
		{
			
			$css_content = preg_replace('#/\*.*?\*/#s','',$css_content);
			
			$css_content = preg_replace_callback('#url\(\'?([^\)\']*)\'?\)#s',array('Tools', 'replaceByAbsoluteURL'),$css_content);
			
			/* url('../img/sitemap-top.gif') */
			
			$css_content = preg_replace('#\s+#',' ',$css_content);
			$css_content = str_replace("\t",'',$css_content);
			$css_content = str_replace("\n",'',$css_content);
			//$css_content = str_replace('}',"}\n",$css_content);
			
			$css_content = str_replace('; ',';',$css_content);
			$css_content = str_replace(': ',':',$css_content);
			$css_content = str_replace(' {','{',$css_content);
			$css_content = str_replace('{ ','{',$css_content);
			$css_content = str_replace(', ',',',$css_content);
			$css_content = str_replace('} ','}',$css_content);
			$css_content = str_replace(' }','}',$css_content);
			$css_content = str_replace(';}','}',$css_content);
			$css_content = str_replace(':0px',':0',$css_content);
			$css_content = str_replace(' 0px',' 0',$css_content);
			$css_content = str_replace(':0em',':0',$css_content);
			$css_content = str_replace(' 0em',' 0',$css_content);
			$css_content = str_replace(':0pt',':0',$css_content);
			$css_content = str_replace(' 0pt',' 0',$css_content);
			$css_content = str_replace(':0%',':0',$css_content);
			$css_content = str_replace(' 0%',' 0',$css_content);
			return trim($css_content);
		}
		return false;
	}
	
	static public function replaceByAbsoluteURL($matches)
		{
			global $current_css_file, $protocol;
			//$protocol.Tools::getMediaServer($url).$url
			if (array_key_exists(1, $matches))
			{
				$tmp = dirname($current_css_file).'/'.$matches[1];
				return 'url(\''.$protocol.Tools::getMediaServer($tmp).$tmp.'\')';
			}
			return false;
		}
	
	static public function addJS($js_uri) {
		global $js_files;

		// avoid useless opération...
		if (in_array($js_uri, $js_files))
			return true;

		//overriding of modules js files
		$different = 0;
		$override_path = str_replace(__PS_BASE_URI__.'modules/', _PS_ROOT_DIR_.'/themes/'._THEME_NAME_.'/js/modules/', $js_uri, $different);
		if ($different && file_exists($override_path))
			$js_uri = str_replace(__PS_BASE_URI__.'modules/', __PS_BASE_URI__.'themes/'._THEME_NAME_.'/js/modules/', $js_uri, $different);

		// detect mass add
		if (!is_array($js_uri))
			$js_uri = array($js_uri);

		// adding file to the big array...
		$js_files = array_merge($js_files, $js_uri);

		return true;
	}

	static public function addCSS($css_uri, $css_media_type = 'all')
	{
		global $css_files;
		
		// avoid useless opération...
		//if (is_array($css_files) && array_key_exists($css_uri, $css_files) && $css_files[$css_uri] == $css_media_type)
		//	return true;

		//overriding of modules css files
		$different = 0;
		$override_path = str_replace(__PS_BASE_URI__.'modules/', _PS_ROOT_DIR_.'/themes/'._THEME_NAME_.'/css/modules/', $css_uri, $different);
		if ($different && file_exists($override_path))
			$css_uri = str_replace(__PS_BASE_URI__.'modules/', __PS_BASE_URI__.'themes/'._THEME_NAME_.'/css/modules/', $css_uri, $different);

		// detect mass add
		if (!is_array($css_uri))
			$css_uri = array($css_uri => $css_media_type);

		// adding file to the big array...
		$css_files = array_merge($css_files, $css_uri);

		return true;
	}
	
	
	/**
	* Combine Compress and Cache CSS (ccc) calls
	*
	*/
	static public function cccCss() {
		global $css_files, $protocol;
		//inits
		$css_files_by_media = array();
		$compressed_css_files = array();
		$compressed_css_files_not_found = array();
		$compressed_css_files_infos = array();
	
		// group css files by media
		foreach ($css_files as $filename => $media)
		{
			if (!array_key_exists($media, $css_files_by_media))
				$css_files_by_media[$media] = array();
		
			$infos = array();
			$infos['uri'] = $filename;
			$url_data = parse_url($filename);
			$infos['path'] = _PS_ROOT_DIR_.str_replace(__PS_BASE_URI__, '/', $url_data['path']);
			$css_files_by_media[$media]['files'][] = $infos;
			if (!array_key_exists('date', $css_files_by_media[$media]))
				$css_files_by_media[$media]['date'] = 0;
			$css_files_by_media[$media]['date'] = max(
				file_exists($infos['path']) ? filemtime($infos['path']) : 0,
				$css_files_by_media[$media]['date']
			);
		
			if (!array_key_exists($media, $compressed_css_files_infos))
				$compressed_css_files_infos[$media] = array('key' => '');
			$compressed_css_files_infos[$media]['key'] .= $filename;
		}
	
		// get compressed css file infos
		foreach ($compressed_css_files_infos as $media => &$info)
		{
			$key = md5($info['key']);
			$filename = _PS_THEME_DIR_.'cache/'.$key.'_'.$media.'.css';
			$info = array(
				'key' => $key,
				'date' => file_exists($filename) ? filemtime($filename) : 0
			);
		}
		// aggregate and compress css files content, write new caches files
		foreach ($css_files_by_media as $media => $media_infos)
		{
			$cache_filename = _PS_THEME_DIR_.'cache/'.$compressed_css_files_infos[$media]['key'].'_'.$media.'.css';
			if ($media_infos['date'] > $compressed_css_files_infos[$media]['date'])
			{
				$compressed_css_files[$media] = '';
				foreach ($media_infos['files'] as $file_infos)
				{
					if (file_exists($file_infos['path']))
						$compressed_css_files[$media] .= Tools::minifyCSS(file_get_contents($file_infos['path']), $file_infos['uri']);
					else
						$compressed_css_files_not_found[] = $file_infos['path'];
				}
				if (!empty($compressed_css_files_not_found))
					$content = '/* WARNING ! file(s) not found : "'.
						implode(',', $compressed_css_files_not_found).
						'" */'."\n".$compressed_css_files[$media];
				else
					$content = $compressed_css_files[$media];
				file_put_contents($cache_filename, $content);
				chmod($cache_filename, 0777);
			}
			$compressed_css_files[$media] = $cache_filename;
		}
	
		// rebuild the original css_files array
		$css_files = array();
		foreach ($compressed_css_files as $media => $filename)
		{
			$url = str_replace(_PS_THEME_DIR_, _THEMES_DIR_._THEME_NAME_.'/', $filename);
			$css_files[$protocol.Tools::getMediaServer($url).$url] = $media;
		}
	}
	
	/**
	* Combine Compress and Cache (ccc) JS calls
	*
	*/
	static public function cccJS() {
		global $js_files, $protocol;
		//inits
		$compressed_js_files_not_found = array();
		$js_files_infos = array();
		$js_files_date = 0;
		$compressed_js_file_date = 0;
		$compressed_js_filename = '';
	
		// get js files infos
		foreach ($js_files as $filename)
		{
			$infos = array();
			$infos['uri'] = $filename;
			$url_data = parse_url($filename);
			$infos['path'] =_PS_ROOT_DIR_.str_replace(__PS_BASE_URI__, '/', $url_data['path']);
			$js_files_infos[] = $infos;
		
			$js_files_date = max(
				file_exists($infos['path']) ? filemtime($infos['path']) : 0,
				$js_files_date
			);
			$compressed_js_filename .= $filename;
		}
	
		// get compressed js file infos
		$compressed_js_filename = md5($compressed_js_filename);
	
		$compressed_js_path = _PS_THEME_DIR_.'cache/'.$compressed_js_filename.'.js';
		$compressed_js_file_date = file_exists($compressed_js_path) ? filemtime($compressed_js_path) : 0;
	
	
		// aggregate and compress js files content, write new caches files
		if ($js_files_date > $compressed_js_file_date)
		{
			$content = '';
			foreach ($js_files_infos as $file_infos)
			{
				if (file_exists($file_infos['path']))
					$content .= file_get_contents($file_infos['path']).';';
				else
					$compressed_js_files_not_found[] = $file_infos['path'];
			}
			$content = Tools::packJS($content);
		
			if (!empty($compressed_js_files_not_found))
				$content = '/* WARNING ! file(s) not found : "'.
					implode(',', $compressed_js_files_not_found).
					'" */'."\n".$content;
			else
				$content = $content;
			file_put_contents($compressed_js_path, $content);
			chmod($compressed_js_path, 0777);
		}
	
		// rebuild the original js_files array
		//$css_files[$protocol.Tools::getMediaServer($url).$url] = $media;
		$url = str_replace(_PS_ROOT_DIR_.'/', __PS_BASE_URI__, $compressed_js_path);
		$js_files = array($protocol.Tools::getMediaServer($url).$url);
	}
	
	static public function getMediaServer($filename)
	{
		$filename = mb_convert_encoding(md5(strtoupper($filename)),"UCS-4BE",'UTF-8');
		$intvalue = 0;
		for($i = 0; $i < mb_strlen($filename,"UCS-4BE"); $i++)
		{
			$s2 = mb_substr($filename,$i,1,"UCS-4BE");
			$val = unpack("N",$s2);
			$intvalue += ($val[1]+$i)*2;
		}
		$nb_server = ($intvalue % 3)+1;
		if (($value = constant('_MEDIA_SERVER_'.$nb_server.'_')) && $value != '')
			return $value;
		return Tools::getHttpHost();
	}

	public static function generateHtaccess($path, $rewrite_settings, $cache_control, $specific = '')
	{
		if (!file_exists($path) || !is_writable($path)) // do nothing
			return true; 

		$tab = array('ErrorDocument' => array(), 'RewriteEngine' => array(), 'RewriteRule' => array());

		// ErrorDocument
		$tab['ErrorDocument']['comment'] = '# Catch 404 errors';
		$tab['ErrorDocument']['content'] = '404 '.__PS_BASE_URI__.'404.php';
		$tab['ErrorDocument']['comment'] = '# Catch 404 errors';
		$tab['ErrorDocument']['content'] = '404 '.__PS_BASE_URI__.'404.php';

		// RewriteEngine
		$tab['RewriteEngine']['comment'] = '# URL rewriting module activation';

		// RewriteRules
		//IMPORTANT : if you change the lines bellow, don"t forget to change the "urlcanonical" module too
		$tab['RewriteRule']['comment'] = '# URL rewriting rules';
		$tab['RewriteRule']['content']['^([a-z0-9]+)\-([a-z0-9]+)(\-[_a-zA-Z0-9-]*)/([_a-zA-Z0-9-]*)\.jpg$'] = 'img/p/$1-$2$3.jpg [L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)\-([0-9]+)/([_a-zA-Z0-9-]*)\.jpg$'] = 'img/p/$1-$2.jpg [L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)(\-[_a-zA-Z0-9-]*)/([_a-zA-Z0-9-]*)\.jpg$'] = 'img/c/$1$2.jpg [L,E]';
		$tab['RewriteRule']['content']['^lang-([a-z]{2})/([a-zA-Z0-9-]*)/([0-9]+)\-([a-zA-Z0-9-]*)\.html(.*)$'] = 'product.php?id_product=$3&isolang=$1$5 [L,E]';
		$tab['RewriteRule']['content']['^lang-([a-z]{2})/([0-9]+)\-([a-zA-Z0-9-]*)\.html(.*)$'] = 'product.php?id_product=$2&isolang=$1$4 [QSA,L,E]';
		$tab['RewriteRule']['content']['^lang-([a-z]{2})/([0-9]+)\-([a-zA-Z0-9-]*)(.*)$'] = 'category.php?id_category=$2&isolang=$1 [QSA,L,E]';
		$tab['RewriteRule']['content']['^([a-zA-Z0-9-]*)/([0-9]+)\-([a-zA-Z0-9-]*)\.html(.*)$'] = 'product.php?id_product=$2$4 [QSA,L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)\-([a-zA-Z0-9-]*)\.html(.*)$'] = 'product.php?id_product=$1$3 [QSA,L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)\-([a-zA-Z0-9-]*)(.*)$'] = 'category.php?id_category=$1 [QSA,L,E]';
		$tab['RewriteRule']['content']['^content/([0-9]+)\-([a-zA-Z0-9-]*)(.*)$'] = 'cms.php?id_cms=$1 [QSA,L,E]';
		$tab['RewriteRule']['content']['^content/category/([0-9]+)\-([a-zA-Z0-9-]*)(.*)$'] = 'cms.php?id_cms_category=$1 [QSA,L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)__([a-zA-Z0-9-]*)(.*)$'] = 'supplier.php?id_supplier=$1$3 [QSA,L,E]';
		$tab['RewriteRule']['content']['^([0-9]+)_([a-zA-Z0-9-]*)(.*)$'] = 'manufacturer.php?id_manufacturer=$1$3 [QSA,L,E]';
		
		Language::loadLanguages();		
		$default_meta = Meta::getMetasByIdLang((int)(Configuration::get('PS_LANG_DEFAULT')));

		foreach (Language::getLanguages() AS $language)
		{													
			foreach (Meta::getMetasByIdLang($language['id_lang']) AS $key => $meta)
			{
				//RewriteRule ^lang-es/contacto$ contact-form.php [QSA,L,E]
				if (!empty($meta['url_rewrite']))
					$tab['RewriteRule']['content']['^lang-'.$language['iso_code'].'/'.$meta['url_rewrite'].'$'] = $meta['page'].'.php?isolang='.$language['iso_code'].' [QSA,L]';
				else if (array_key_exists($key, $default_meta) && $default_meta[$key]['url_rewrite'] != '')
					$tab['RewriteRule']['content']['^lang-'.$language['iso_code'].'/'.$default_meta[$key]['url_rewrite'].'$'] = $default_meta[$key]['page'].'.php?isolang='.$language['iso_code'].' [QSA,L]';					
			}
		}						
		
		$tab['RewriteRule']['content']['^lang-([a-z]{2})/(.*)$'] = '$2?isolang=$1 [QSA,L,E]';

		if (!$writeFd = @fopen($path, 'w'))
			return false;
		else
		{
			// PS Comments
			fwrite($writeFd, "# .htaccess automaticaly generated by PrestaShop e-commerce open-source solution\n");
			fwrite($writeFd, "# WARNING: PLEASE DO NOT MODIFY THIS FILE MANUALLY. IF NECESSARY, ADD YOUR SPECIFIC CONFIGURATION WITH THE HTACCESS GENERATOR IN BACK OFFICE\n");
			fwrite($writeFd, "# http://www.prestashop.com - http://www.prestashop.com/forums\n\n");
			if (!empty($specific))
				fwrite($writeFd, $specific);
			
			// RewriteEngine
			fwrite($writeFd, "\n<IfModule mod_rewrite.c>\n");
			fwrite($writeFd, $tab['RewriteEngine']['comment']."\nRewriteEngine on\n\n");
			fwrite($writeFd, $tab['RewriteRule']['comment']."\n");
			// Webservice
			fwrite($writeFd, 'RewriteRule ^api/?(.*)$ '.__PS_BASE_URI__."webservice/dispatcher.php?url=$1 [QSA,L,E]\n");
			
			// Classic URL rewriting
			if ($rewrite_settings)
				foreach ($tab['RewriteRule']['content'] as $rule => $url)
					fwrite($writeFd, 'RewriteRule '.$rule.' '.__PS_BASE_URI__.$url."\n");
			
			fwrite($writeFd, "</IfModule>\n\n");
			
			// ErrorDocument
			fwrite($writeFd, $tab['ErrorDocument']['comment']."\nErrorDocument ".$tab['ErrorDocument']['content']."\n");
			
			// Cache control
			if ($cache_control)
			{
				$cacheControl = "
<IfModule mod_expires.c>
	ExpiresActive On
	ExpiresByType image/gif \"access plus 1 month\"
	ExpiresByType image/jpeg \"access plus 1 month\"
	ExpiresByType image/png \"access plus 1 month\"
	ExpiresByType text/css \"access plus 1 week\"
	ExpiresByType text/javascript \"access plus 1 week\"
	ExpiresByType application/javascript \"access plus 1 week\"
	ExpiresByType application/x-javascript \"access plus 1 week\"
	ExpiresByType image/x-icon \"access plus 1 year\"
</IfModule>

FileETag INode MTime Size
<IfModule mod_deflate.c>
	AddOutputFilterByType DEFLATE text/html
	AddOutputFilterByType DEFLATE text/css
	AddOutputFilterByType DEFLATE text/javascript
	AddOutputFilterByType DEFLATE application/javascript
	AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
					";
				fwrite($writeFd, $cacheControl);
			}

			fclose($writeFd);

			return true;
		}
	}


	static public function jsonEncode($json)
	{
		if (function_exists('json_encode'))
			return json_encode($json);
		else
		{
			if (is_null($json)) return 'null';
			if ($json === false) return 'false';
			if ($json === true) return 'true';
			if (is_scalar($json))
			{
			  if (is_float($json))
			  {
			    // Always use "." for floats.
			    return (float)(str_replace(",", ".", strval($json)));
			  }
			
			  if (is_string($json))
			  {
			    static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
			    return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $json) . '"';
			  }
			  else
			    return $json;
			}
			$isList = true;
			for ($i = 0, reset($json); $i < count($json); $i++, next($json))
			{
			  if (key($json) !== $i)
			  {
			    $isList = false;
			    break;
			  }
			}
			$result = array();
			if ($isList)
			{
			  foreach ($json as $v) $result[] = self::jsonEncode($v);
			  return '[' . join(',', $result) . ']';
			}
			else
			{
			  foreach ($json as $k => $v) $result[] = self::jsonEncode($k).':'.self::jsonEncode($v);
			  return '{' . join(',', $result) . '}';
			}
		}
	}
	
	/**
	 * Display a warning message indicating that the method is deprecated
	 */
	public static function displayAsDeprecated() 
	{
		if (_PS_DISPLAY_COMPATIBILITY_WARNING_)
		{
			$backtrace = debug_backtrace();
			$callee = next($backtrace);
	   		trigger_error('Function <strong>'.$callee['function'].'()</strong> is deprecated in <strong>'.$callee['file'].'</strong> on line <strong>'.$callee['line'].'</strong>', E_USER_WARNING);
		}
	}

	public static function enableCache($level = 1)
	{
		global $smarty;

		if (!Configuration::get('PS_SMARTY_CACHE'))
			return;
		if ($smarty->force_compile == 0 AND $smarty->caching == $level)
			return ;
		self::$_forceCompile = (int)($smarty->force_compile);
		self::$_caching = (int)($smarty->caching);
		$smarty->force_compile = 0;
		$smarty->caching = (int)($level);
	}

	public static function restoreCacheSettings()
	{
		global $smarty;

		$smarty->force_compile = (int)(self::$_forceCompile);
		$smarty->caching = (int)(self::$_caching);
	}
	
	public static function isCallable($function)
	{
		$disabled = explode(',', ini_get('disable_functions'));
		return (!in_array($function, $disabled) AND is_callable($function));
	}
}

/**
* Compare 2 prices to sort products
*
* @param float $a
* @param float $b
* @return integer
*/
/* Externalized because of a bug in PHP 5.1.6 when inside an object */
function cmpPriceAsc($a,$b)
{
	if ((float)($a['price_tmp']) < (float)($b['price_tmp']))
		return (-1);
	elseif ((float)($a['price_tmp']) > (float)($b['price_tmp']))
		return (1);
	return (0);
}

function cmpPriceDesc($a,$b)
{
	if ((float)($a['price_tmp']) < (float)($b['price_tmp']))
		return (1);
	elseif ((float)($a['price_tmp']) > (float)($b['price_tmp']))
		return (-1);
	return (0);
}


