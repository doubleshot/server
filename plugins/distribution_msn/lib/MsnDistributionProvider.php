<?php
class MsnDistributionProvider implements IDistributionProvider
{
	/**
	 * @var MsnDistributionProvider
	 */
	protected static $instance;
	
	protected function __construct()
	{
		
	}
	
	/**
	 * @return MsnDistributionProvider
	 */
	public static function get()
	{
		if(!self::$instance)
			self::$instance = new MsnDistributionProvider();
			
		return self::$instance;
	}
	
	/* (non-PHPdoc)
	 * @see IDistributionProvider::getType()
	 */
	public function getType()
	{
		return MsnDistributionProviderType::get()->coreValue(MsnDistributionProviderType::MSN);
	}
	
	/**
	 * @return string
	 */
	public function getName()
	{
		return 'MSN';
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::isDeleteEnabled()
	 */
	public function isDeleteEnabled()
	{
		return true;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::isUpdateEnabled()
	 */
	public function isUpdateEnabled()
	{
		return true;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::isReportsEnabled()
	 */
	public function isReportsEnabled()
	{
		return true;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::isScheduleUpdateEnabled()
	 */
	public function isScheduleUpdateEnabled()
	{
		// TODO Not clear from the docs, will be decided in the dev process
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::useDeleteInsteadOfUpdate()
	 */
	public function useDeleteInsteadOfUpdate()
	{
		return false;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::getJobIntervalBeforeSunrise()
	 */
	public function getJobIntervalBeforeSunrise()
	{
//		maybe should be taken from local config and not kConf
		if(kConf::hasParam('msn_distribution_interval_before_sunrise'))
			return kConf::get('msn_distribution_interval_before_sunrise');
			
		return 0;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::getJobIntervalBeforeSunset()
	 */
	public function getJobIntervalBeforeSunset()
	{
//		maybe should be taken from local config and not kConf
		if(kConf::hasParam('msn_distribution_interval_before_sunset'))
			return kConf::get('msn_distribution_interval_before_sunset');
			
		return 0;
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::getUpdateRequiredEntryFields()
	 */
	public function getUpdateRequiredEntryFields()
	{
//		e.g.
//		maybe should be taken from local config or kConf
//		return array(entryPeer::NAME, entryPeer::DESCRIPTION);

		return array();
	}

	/* (non-PHPdoc)
	 * @see IDistributionProvider::getUpdateRequiredMetadataXPaths()
	 */
	public function getUpdateRequiredMetadataXPaths()
	{
//		e.g.
//		maybe should be taken from local config or kConf
//		return array(
//			"/*[local-name()='metadata']/*[local-name()='ShortDescription']",
//			"/*[local-name()='metadata']/*[local-name()='LongDescription']",
//		);
		
		return array();
	}
	
	public static function generateDeleteXML($entryId, $remoteId)
	{
		
	}
	
	public static function generateUpdateXML($entryId, $remoteId)
	{
		
	}
	
	public static function generateSubmitXML($entryId, KalturaMsnDistributionJobProviderData $providerData)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		$mrss = kMrssManager::getEntryMrss($entry);
		if(!$mrss)
			return null;
			
		$xml = new DOMDocument();
		if(!$xml->loadXML($mrss))
			return null;
		
		$xslPath = dirname(__FILE__) . '/../../xml/submit.xsl';
		$xsl = new DOMDocument();
		$xsl->load($xslPath);
			
		// set variables in the xsl
		$varNodes = $xsl->getElementsByTagName('variable');
		foreach($varNodes as $varNode)
		{
			$nameAttr = $varNode->attributes->getNamedItem('name');
			if(!$nameAttr)
				continue;
				
			$name = $nameAttr->value;
			if($name && $providerData->$name)
			{
				$varNode->textContent = $providerData->$name;
				$varNode->appendChild($xsl->createTextNode($providerData->$name));
				KalturaLog::debug("Set variable [$name] to [{$providerData->$name}]");
			}
		}

		$proc = new XSLTProcessor;
		$proc->importStyleSheet($xsl);
		
		$xml = $proc->transformToDoc($xml);
		if(!$xml)
			return null;
			
		$xsdPath = dirname(__FILE__) . '/../../xml/submit.xsd';
		if($xsdPath && !$xml->schemaValidate($xsdPath))		
			return null;
		
		return $xml->saveXML();
	}
}