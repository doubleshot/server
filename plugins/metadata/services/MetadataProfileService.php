<?php
/**
 * Metadata Profile service
 *
 * @service metadataProfile
 * @package plugins.metadata
 * @subpackage api.services
 */
class MetadataProfileService extends KalturaBaseService
{
	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);

		myPartnerUtils::addPartnerToCriteria(new MetadataProfilePeer(), $this->getPartnerId(), $this->private_partner_data, $this->partnerGroup());
		myPartnerUtils::addPartnerToCriteria(new MetadataPeer(), $this->getPartnerId(), $this->private_partner_data, $this->partnerGroup());
		myPartnerUtils::addPartnerToCriteria(new entryPeer(), $this->getPartnerId(), $this->private_partner_data, $this->partnerGroup());
//		myPartnerUtils::addPartnerToCriteria(new FileSyncPeer(), $this->getPartnerId(), $this->private_partner_data, $this->partnerGroup());
		
		if(!MetadataPlugin::isAllowedPartner($this->getPartnerId()))
			throw new KalturaAPIException(KalturaErrors::SERVICE_FORBIDDEN, $this->serviceName.'->'.$this->actionName);
	}
		
	/**
	 * Allows you to add a metadata profile object and metadata profile content associated with Kaltura object type
	 * 
	 * @action add
	 * @param KalturaMetadataProfile $metadataProfile
	 * @param string $xsdData XSD metadata definition
	 * @param string $viewsData UI views definition
	 * @return KalturaMetadataProfile
	 */
	function addAction(KalturaMetadataProfile $metadataProfile, $xsdData, $viewsData = null)
	{
		kMetadataManager::validateMetadataProfileField($this->getPartnerId(), $xsdData, false, $metadataProfile->metadataObjectType);
		$dbMetadataProfile = $metadataProfile->toInsertableObject();
		$dbMetadataProfile->setStatus(KalturaMetadataProfileStatus::ACTIVE);
		$dbMetadataProfile->setPartnerId($this->getPartnerId());
		$dbMetadataProfile->save();
		
		$xsdData = html_entity_decode($xsdData);
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		kFileSyncUtils::file_put_contents($key, $xsdData);
		
		if($viewsData)
		{
			$viewsData = html_entity_decode($viewsData);
			$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_VIEWS);
			kFileSyncUtils::file_put_contents($key, $viewsData);
		}
		kMetadataManager::parseProfileSearchFields($this->getPartnerId(), $dbMetadataProfile);
		
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}
	
	/**
	 * Allows you to add a metadata profile object and metadata profile file associated with Kaltura object type
	 * 
	 * @action addFromFile
	 * @param KalturaMetadataProfile $metadataProfile
	 * @param file $xsdFile XSD metadata definition
	 * @param file $viewsFile UI views definition
	 * @return KalturaMetadataProfile
	 * @throws MetadataErrors::METADATA_FILE_NOT_FOUND
	 */
	function addFromFileAction(KalturaMetadataProfile $metadataProfile, $xsdFile, $viewsFile = null)
	{
		$filePath = $xsdFile['tmp_name'];
		if(!file_exists($filePath))
			throw new KalturaAPIException(MetadataErrors::METADATA_FILE_NOT_FOUND, $xsdFile['name']);
		
		kMetadataManager::validateMetadataProfileField($this->getPartnerId(), $xsdFile, false, $metadataProfile->metadataObjectType);
		$dbMetadataProfile = $metadataProfile->toInsertableObject();
		$dbMetadataProfile->setStatus(KalturaMetadataProfileStatus::ACTIVE);
		$dbMetadataProfile->setPartnerId($this->getPartnerId());
		$dbMetadataProfile->save();
		
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		kFileSyncUtils::moveFromFile($filePath, $key);
		
		if($viewsFile && $viewsFile['size'])
		{
			$filePath = $viewsFile['tmp_name'];
			if(!file_exists($filePath))
				throw new KalturaAPIException(MetadataErrors::METADATA_FILE_NOT_FOUND, $viewsFile['name']);
				
			$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_VIEWS);
			kFileSyncUtils::moveFromFile($filePath, $key);
		}
		kMetadataManager::parseProfileSearchFields($this->getPartnerId(), $dbMetadataProfile);
		
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}
	
	/**
	 * Retrieve a metadata profile object by id
	 * 
	 * @action get
	 * @param int $id 
	 * @return KalturaMetadataProfile
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 */		
	function getAction($id)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK( $id );
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
			
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}
	
	/**
	 * Update an existing metadata object
	 * 
	 * @action update
	 * @param int $id 
	 * @param KalturaMetadataProfile $metadataProfile
	 * @param string $xsdData XSD metadata definition
	 * @param string $viewsData UI views definition
	 * @return KalturaMetadataProfile
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws MetadataErrors::METADATA_UNABLE_TO_TRANSFORM
	 * @throws MetadataErrors::METADATA_TRANSFORMING
	 */	
	function updateAction($id, KalturaMetadataProfile $metadataProfile, $xsdData = null, $viewsData = null)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK($id);
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		
		if($dbMetadataProfile->getStatus() != MetadataProfile::STATUS_ACTIVE)
			throw new KalturaAPIException(MetadataErrors::METADATA_TRANSFORMING);

		kMetadataManager::validateMetadataProfileField($this->getPartnerId(), $xsdData, false, $dbMetadataProfile->getObjectType(), $id);	
		
		$dbMetadataProfile = $metadataProfile->toUpdatableObject($dbMetadataProfile);
		
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		$oldXsd = kFileSyncUtils::getLocalFilePathForKey($key);
		$oldVersion = $dbMetadataProfile->getVersion();
		
		if($xsdData)
		{
			$xsdData = html_entity_decode($xsdData);
			$dbMetadataProfile->incrementVersion();
		}
			
		if(!is_null($viewsData) && $viewsData != '')
		{
			$viewsData = html_entity_decode($viewsData);
			$dbMetadataProfile->incrementViewsVersion();
		}
			
		if($xsdData)
		{
		    $xsdPath = sys_get_temp_dir() . '/' . uniqid() . '.xsd';
		    file_put_contents($xsdPath, $xsdData);
		    
			try
			{
				kMetadataManager::diffMetadataProfile($dbMetadataProfile, $oldVersion, $oldXsd, $dbMetadataProfile->getVersion(), $xsdPath);
			}
			catch(kXsdException $e)
			{
				throw new KalturaAPIException(MetadataErrors::METADATA_UNABLE_TO_TRANSFORM, $e->getMessage());
			}
			
			$dbMetadataProfile->save();
			
			$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
			kFileSyncUtils::moveFromFile($xsdPath, $key);
		}
		else
		{
			$dbMetadataProfile->save();
		}
		
		if(!is_null($viewsData) && $viewsData != '')
		{
			$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_VIEWS);
			kFileSyncUtils::file_put_contents($key, $viewsData);
		}
	
		kMetadataManager::parseProfileSearchFields($this->getPartnerId(), $dbMetadataProfile);
		
		$metadataProfile->fromObject($dbMetadataProfile);
		return $metadataProfile;
	}	
	
	
	/**
	 * List metadata profile objects by filter and pager
	 * 
	 * @action list
	 * @param KalturaMetadataProfileFilter $filter
	 * @param KalturaFilterPager $pager
	 * @return KalturaMetadataProfileListResponse
	 */
	function listAction(KalturaMetadataProfileFilter $filter = null, KalturaFilterPager $pager = null)
	{
		if (!$filter)
			$filter = new KalturaMetadataProfileFilter;
			
		$metadataProfileFilter = new MetadataProfileFilter();
		$filter->toObject($metadataProfileFilter);
		
		$c = new Criteria();
		$metadataProfileFilter->attachToCriteria($c);
		$count = MetadataProfilePeer::doCount($c);
		
		if (! $pager)
			$pager = new KalturaFilterPager ();
		$pager->attachToCriteria ( $c );
		$list = MetadataProfilePeer::doSelect($c);
		
		$response = new KalturaMetadataProfileListResponse();
		$response->objects = KalturaMetadataProfileArray::fromMetadataProfileArray($list);
		$response->totalCount = $count;
		
		return $response;
	}
	
	/**
	 * List metadata profile fields by metadata profile id
	 * 
	 * @action listFields
	 * @param int $metadataProfileId
	 * @return KalturaMetadataProfileFieldListResponse
	 */
	function listFieldsAction($metadataProfileId)
	{
		$dbFields = MetadataProfileFieldPeer::retrieveActiveByMetadataProfileId($metadataProfileId);
		
		$response = new KalturaMetadataProfileFieldListResponse();
		$response->objects = KalturaMetadataProfileFieldArray::fromMetadataProfileFieldArray($dbFields);
		$response->totalCount = count($dbFields);
		
		return $response;
	}
	
	/**
	 * Delete an existing metadata profile
	 * 
	 * @action delete
	 * @param int $id
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 */		
	function deleteAction($id)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK($id);
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		
		$dbMetadataProfile->setStatus(KalturaMetadataProfileStatus::DEPRECATED);
		$dbMetadataProfile->save();
		
		$c = new Criteria();
		$c->add(MetadataProfileFieldPeer::METADATA_PROFILE_ID, $id);
		$c->add(MetadataProfileFieldPeer::STATUS, MetadataProfileField::STATUS_DEPRECATED, Criteria::NOT_EQUAL);
		$MetadataProfileFields = MetadataProfileFieldPeer::doSelect($c);
		
		foreach($MetadataProfileFields as $MetadataProfileField)
		{
			$MetadataProfileField->setStatus(MetadataProfileField::STATUS_DEPRECATED);
			$MetadataProfileField->save();
		}
		
		$c = new Criteria();
		$c->add(MetadataPeer::METADATA_PROFILE_ID, $id);
		$c->add(MetadataPeer::STATUS, KalturaMetadataStatus::DELETED, Criteria::NOT_EQUAL);
	
		$peer = null;
		MetadataPeer::setUseCriteriaFilter(false);
		$metadatas = MetadataPeer::doSelect($c);
		foreach($metadatas as $metadata)
			kEventsManager::raiseEvent(new kObjectDeletedEvent($metadata));
		
		$update = new Criteria();
		$update->add(MetadataPeer::STATUS, KalturaMetadataStatus::DELETED);
			
		$con = Propel::getConnection(MetadataPeer::DATABASE_NAME, Propel::CONNECTION_READ);
		BasePeer::doUpdate($c, $update, $con);
	}
	
	/**
	 * Update an existing metadata object definition file
	 * 
	 * @action revert
	 * @param int $id 
	 * @param int $toVersion
	 * @return KalturaMetadataProfile
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws MetadataErrors::METADATA_FILE_NOT_FOUND
	 * @throws MetadataErrors::METADATA_UNABLE_TO_TRANSFORM
	 */	
	function revertAction($id, $toVersion)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK($id);
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
	
		$oldKey = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION, $toVersion);
		if(!kFileSyncUtils::fileSync_exists($oldKey))
			throw new KalturaAPIException(MetadataErrors::METADATA_FILE_NOT_FOUND, $oldKey);
		
		$dbMetadataProfile->incrementVersion();
		$dbMetadataProfile->save();
		
		$versionGap = $dbMetadataProfile->getVersion() - $toVersion;
		
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		kFileSyncUtils::createSyncFileLinkForKey($key, $oldKey);
		
		kMetadataManager::parseProfileSearchFields($this->getPartnerId(), $dbMetadataProfile);
		
		MetadataPeer::setUseCriteriaFilter(false);
		$metadatas = MetadataPeer::retrieveByProfile($id, $toVersion);
		foreach($metadatas as $metadata)
		{
			// validate object exists
			$object = kMetadataManager::getObjectFromPeer($metadata);
			if(!$object)
				continue;
				
			$metadata->incrementVersion();
			$oldKey = $metadata->getSyncKey(Metadata::FILE_SYNC_METADATA_DATA, $metadata->getVersion() - $versionGap);
			if(!kFileSyncUtils::fileSync_exists($oldKey))
				continue;
			
			$xml = kFileSyncUtils::file_get_contents($oldKey, true, false);
			if(!$xml)
				continue;
			
			$errorMessage = '';
			if(!kMetadataManager::validateMetadata($dbMetadataProfile->getId(), $xml, $errorMessage))
				continue;
			
			$metadata->setMetadataProfileVersion($dbMetadataProfile->getVersion());
			$metadata->setStatus(Metadata::STATUS_VALID);
			$metadata->save();
			
			$key = $metadata->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
			kFileSyncUtils::createSyncFileLinkForKey($key, $oldKey);
		}
		
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}	
	
	/**
	 * Update an existing metadata object definition file
	 * 
	 * @action updateDefinitionFromFile
	 * @param int $id 
	 * @param file $xsdFile XSD metadata definition
	 * @return KalturaMetadataProfile
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws MetadataErrors::METADATA_FILE_NOT_FOUND
	 * @throws MetadataErrors::METADATA_UNABLE_TO_TRANSFORM
	 */	
	function updateDefinitionFromFileAction($id, $xsdFile)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK($id);
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
	
		$filePath = null;
		if($xsdFile)
		{
			$filePath = $xsdFile['tmp_name'];
			if(!file_exists($filePath))
				throw new KalturaAPIException(MetadataErrors::METADATA_FILE_NOT_FOUND, $xsdFile['name']);
		}
		
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		$oldXsd = kFileSyncUtils::getLocalFilePathForKey($key);
		$oldVersion = $dbMetadataProfile->getVersion();
		
		$dbMetadataProfile->incrementVersion();
		
		try
		{
			kMetadataManager::diffMetadataProfile($dbMetadataProfile, $oldVersion, $oldXsd, $dbMetadataProfile->getVersion(), $filePath);
		}
		catch(kXsdException $e)
		{
			throw new KalturaAPIException(MetadataErrors::METADATA_UNABLE_TO_TRANSFORM);
		}
		
		$dbMetadataProfile->save();
		
		$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_DEFINITION);
		kFileSyncUtils::moveFromFile($filePath, $key);
		
		kMetadataManager::parseProfileSearchFields($this->getPartnerId(), $dbMetadataProfile);
		
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}
	
	/**
	 * Update an existing metadata object views file
	 * 
	 * @action updateViewsFromFile
	 * @param int $id 
	 * @param file $viewsFile UI views file
	 * @return KalturaMetadataProfile
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws MetadataErrors::METADATA_FILE_NOT_FOUND
	 */	
	function updateViewsFromFileAction($id, $viewsFile)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK($id);
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
	
		$filePath = null;
		if($viewsFile)
		{
			$filePath = $viewsFile['tmp_name'];
			if(!file_exists($filePath))
				throw new KalturaAPIException(MetadataErrors::METADATA_FILE_NOT_FOUND, $viewsFile['name']);
		}
		
		$dbMetadataProfile->incrementViewsVersion();
		$dbMetadataProfile->save();
		
		if(trim(file_get_contents($filePath)) != '')
		{
			$key = $dbMetadataProfile->getSyncKey(MetadataProfile::FILE_SYNC_METADATA_VIEWS);
			kFileSyncUtils::moveFromFile($filePath, $key);
		}
		
		$metadataProfile = new KalturaMetadataProfile();
		$metadataProfile->fromObject($dbMetadataProfile);
		
		return $metadataProfile;
	}	

	/**
	 * Serves metadata profile XSD file
	 *  
	 * @action serve
	 * @param int $id
	 * @return file
	 *  
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws KalturaErrors::FILE_DOESNT_EXIST
	 */
	public function serveAction($id)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK( $id );
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		
		$fileName = $dbMetadataProfile->getSystemName() . '.xml';
		$fileSubType = MetadataProfile::FILE_SYNC_METADATA_DEFINITION;
		
		return $this->serveFile($dbMetadataProfile, $fileSubType, $fileName);
	}	

	/**
	 * Serves metadata profile view file
	 *  
	 * @action serveView
	 * @param int $id
	 * @return file
	 *  
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws KalturaErrors::FILE_DOESNT_EXIST
	 */
	public function serveViewAction($id)
	{
		$dbMetadataProfile = MetadataProfilePeer::retrieveByPK( $id );
		
		if(!$dbMetadataProfile)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		
		$fileName = $dbMetadataProfile->getSystemName() . '.xml';
		$fileSubType = MetadataProfile::FILE_SYNC_METADATA_VIEWS;
		
		return $this->serveFile($dbMetadataProfile, $fileSubType, $fileName);
	}
}
