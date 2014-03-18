<?php


/**
 * @package Core
 * @subpackage model
 */
abstract class DeliveryProfile extends BaseDeliveryProfile {
	
	// @_!! TODO Set 82 back to be default!
	
	protected $DEFAULT_RENDERER_CLASS = 'kF4MManifestRenderer';
	
	/**
	 * @var DeliveryProfileDynamicAttributes
	 */
	protected $params;

	public function __construct()
	{
		parent::__construct();
		$this->params = new DeliveryProfileDynamicAttributes();
	}
	
	public function cloneToNew ( $newObject )
	{
		// TODO @_!! Asd T about fillObjectFromObject Usage
		$newObject->setCopiedFrom($this);
	
		$all_fields = DeliveryProfilePeer::getFieldNames ();
		$ignore_list = array ( "Id" , "ParentId", "IsDefault");
		
		// clone from current
		baseObjectUtils::fillObjectFromObject( $all_fields ,
				$this ,
				$newObject ,
				baseObjectUtils::CLONE_POLICY_PREFER_NEW , $ignore_list , BasePeer::TYPE_PHPNAME );
	
		$newObject->save(null, true);
		return $newObject;
	}
	
	public function initDeliveryDynamicAttribtues(FileSync $fileSync = null, flavorAsset $flavorAsset = null) {
		if ($flavorAsset)
			$this->params->setContainerFormat($flavorAsset->getContainerFormat());
	
		if($flavorAsset && $flavorAsset->getFileExt() !== null) // if the extension is missing use the one from the actual path
			$this->params->setFileExtention($flavorAsset->getFileExt());
		else if ($fileSync) 
			$this->params->setFileExtention(pathinfo($fileSync->getFilePath(), PATHINFO_EXTENSION));
	}
	
	public function setDynamicAttribtues(DeliveryProfileDynamicAttributes $params) {
		$this->params->cloneAttributes($params);
	}
	// -------------------------------------
	// -- Override base methods ------------
	// -------------------------------------
	
	/**
	 * @return kUrlRecognizer
	 */
	public function getRecognizer()
	{
		$serializedObject = parent::getRecognizer();
		try {
			$object = unserialize($serializedObject);
		}
		catch (Exception $e) {
			KalturaLog::err('Error unserializing recognizer for delivery id ['.$this->getId().']');
			$object = null;
		}
		if ($object instanceof kUrlRecognizer) {
			return $object;
		}
		return null;
	}
	
	/**
	 * @param kUrlRecognizer $newObject
	 */
	public function setRecognizer($newObject)
	{
		if ($newObject instanceof kUrlRecognizer)
		{
			$serializedObject = serialize($newObject);
			parent::setRecognizer($serializedObject);
		}
		else
		{
			KalturaLog::err('Given input is not an instance of kUrlRecognizer - ignoring');
		}
	}	
	
	/**
	 * @return kUrlRecognizer
	 */
	public function getTokenizer()
	{
		$serializedObject = parent::getTokenizer();
		
		try {
			$object = unserialize($serializedObject);
		}
		catch (Exception $e) {
			KalturaLog::err('Error unserializing tokenizer for delivery id ['.$this->getId().']');
			$object = null;
		}
		
		if ($object instanceof kUrlTokenizer) {
			return $object;
		}
		return null;
	}
	
	/**
	 * @param kUrlTokenizer $newObject
	 */
	public function setTokenizer($newObject)
	{
		if ($newObject instanceof kUrlTokenizer)
		{
			$serializedObject = serialize($newObject);
			parent::setTokenizer($serializedObject);
		}
		else
		{
			KalturaLog::err('Given input is not an instance of kUrlTokenizer - ignoring');
		}
	}
	
	public function setUrl($url) {
		$hostName = parse_url($url, PHP_URL_HOST);
		if(is_null($hostName)) {
			$path = explode("/", $url);
			$hostName = $path[0];
		}
		$this->setHostName($hostName);
		parent::setUrl($url);
	}
	
	public function setEntryId($entryId) {
		return $this->params->setEntryId($entryId);
	}
	
	public function setStorageProfileId($storageProfileId) {
		return $this->params->setStorageProfileId($storageProfileId);
	}
	
	// -------------------------------------
	// -----  DeliveryProfile functionality--------
	// -------------------------------------
	
	/**
	 * @param string baseUrl
	 * @param array $flavorUrls
	 */
	public function finalizeUrls(&$baseUrl, &$flavorsUrls)
	{
		return;
	}
	
	/**
	 * @param flavorAsset $asset
	 * @param string $clientTag
	 * @return string
	 */
	public function getPlayManifestUrl(flavorAsset $asset, $clientTag)
	{
		$entryId = $asset->getEntryId();
		$partnerId = $asset->getPartnerId();
		$subpId = $asset->getentry()->getSubpId();
		$partnerPath = myPartnerUtils::getUrlForPartner($partnerId, $subpId);
		$flavorAssetId = $asset->getId();
		$cdnHost = parse_url($this->domain, PHP_URL_HOST);
	
		$url = "$partnerPath/playManifest/entryId/$entryId/flavorId/$flavorAssetId/protocol/{$this->params->getMediaProtocol()}/format/url/cdnHost/$cdnHost/clientTag/$clientTag";
		if($this->params->getStorageProfileId())
			$url .= "/storageId/" . $this->params->getStorageProfileId();
	
		return $url;
	}
	
	/**
	 * check whether this url manager sent the current request.
	 * if so, return a string describing the usage. e.g. cdn.acme.com+token for
	 * using cdn.acme.com with secure token delivery. This string can be matched to the
	 * partner settings in order to enforce a specific delivery method.
	 * @return string
	 */
	public function identifyRequest() {
		$delivery = @$_SERVER['HTTP_X_FORWARDED_HOST'];
		if (!$delivery)
			$delivery = @$_SERVER['HTTP_HOST'];
		
		$hosts = array();
		if(!is_null($this->getRecognizer())) {
			$hostsList = $this->getRecognizer()->getHosts();
			if(is_null($hostsList))
				return false;
			$hosts = explode(",",$hostsList );
		}
			
		if (!in_array($delivery, $hosts))
			return false;
		
		$uri = $_SERVER["REQUEST_URI"];
		if (strpos($uri, "/s/") === 0)
			$delivery .= "+token";
		
		return $delivery;
	}
	
	/**
	 * @param array $flavors
	 * @return string
	 */
	protected function getMimeType($flavors)
	{
		$entry = entryPeer::retrieveByPK($this->params->getEntryId());
		if ($entry->getType() == entryType::MEDIA_CLIP && count($flavors))
		{
			$isMp3 = true;
			foreach($flavors as $flavor)
			{
				if (!isset($flavor['ext']) || strtolower($flavor['ext']) != 'mp3')
					$isMp3 = false;
			}
				
			if ($isMp3)
				return 'audio/mpeg';
		}
	
		return 'video/x-flv';
	}
	
	// -------------------------------------
	// -----   Serve functionality  --------
	// -------------------------------------
	
	protected function getRendererClass() {
		return $this->DEFAULT_RENDERER_CLASS;
	}
	
	protected function getRenderer($flavors)
	{
		$class = null;
		if ($this->params->getResponseFormat())
		{
			$formatMapping = array(
					'f4m' => 	'kF4MManifestRenderer',
					'f4mv2' => 	'kF4Mv2ManifestRenderer',
					'smil' => 	'kSmilManifestRenderer',
					'm3u8' => 	'kM3U8ManifestRenderer',
					'jsonp' => 	'kJSONPManifestRenderer',
					'redirect' => 'kRedirectManifestRenderer',
			);
	
			if (isset($formatMapping[$this->params->getResponseFormat()]))
				$class = $formatMapping[$this->params->getResponseFormat()];
		}
	
		if (!$class)
			$class = $this->getRendererClass();
	
		$renderer = new $class;
		if ($renderer instanceof kMultiFlavorManifestRenderer)
			$renderer->flavors = $flavors;
		else
			$renderer->flavor = reset($flavors);
	
		return $renderer;
	}
	
	/**
	 * @param string $url
	 * @param string $urlPrefix
	 * @param flavorAsset $flavorAsset
	 * @return array
	 */
	protected function getFlavorAssetInfo($url, $urlPrefix = '', flavorAsset $flavorAsset = null)
	{
		$ext = null;
		if ($flavorAsset)
		{
			$ext = $flavorAsset->getFileExt();
		}
		if (!$ext)
		{
			$urlPath = parse_url($urlPrefix . $url, PHP_URL_PATH);
			$ext = pathinfo($urlPath, PATHINFO_EXTENSION);
		}
	
		$bitrate = ($flavorAsset ? $flavorAsset->getBitrate() : 0);
		$width =   ($flavorAsset ? $flavorAsset->getWidth()   : 0);
		$height =  ($flavorAsset ? $flavorAsset->getHeight()  : 0);
	
		return array(
				'url' => $url,
				'urlPrefix' => $urlPrefix,
				'ext' => $ext,
				'bitrate' => $bitrate,
				'width' => $width,
				'height' => $height);
	}
	
} 
