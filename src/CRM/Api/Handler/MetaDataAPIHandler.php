<?php

namespace Zoho\CRM\Api\Handler;

use Zoho\CRM\Api\Handler\APIHandler;
use Zoho\CRM\Api\Handler\ModuleAPIHandler;
use Zoho\CRM\Common\APIConstants;
use Zoho\CRM\Api\APIRequest;
use Zoho\CRM\Crud\ZCRMModule;
use Zoho\CRM\Setup\Users\ZCRMUser;
use Zoho\CRM\Setup\Users\ZCRMProfile;
use Zoho\CRM\Crud\ZCRMModuleRelatedList;
use Zoho\CRM\Crud\ZCRMRelatedListProperties;
use Zoho\CRM\Crud\ZCRMCustomView;
use Zoho\CRM\Crud\ZCRMCustomViewCriteria;
use Zoho\CRM\Exception\ZCRMException;
use Zoho\CRM\Exception\APIExceptionHandler;

class MetaDataAPIHandler extends APIHandler {

    /**
     * @var MetaDataAPIHandler
     */
    protected static $instance;
    
    /**
     * @param InterfaceConfigurations $configs
     * @param string $accessToken
     * @return MetaDataAPIHandler
     */
    public static function getInstance($configs, $accessToken) {
        if (is_null(static::$instance)) :
            static::$instance = new static($configs, $accessToken);
        endif;

        return static::$instance;
    }

    /**
     * @param InterfaceConfigurations $configs
     * @param string $accessToken
     */
    protected function __construct($configs, $accessToken) {
        $this->configs = $configs;
        $this->apiKey = $accessToken;
    }
    
    /**
     * @return BulkAPIResponse
     * @throws ZCRMException
     */
    public function getAllModules() {
        try {
            $this->requestMethod = 'GET';
            $this->urlPath = 'settings/modules';
            $this->addHeader('Content-Type', 'application/json');
            $apiRequest = new APIRequest($this);
            $responseInstance = $apiRequest->getBulkAPIResponse();
            $responseJSON = $responseInstance->getResponseJSON();
            $modulesArray = $responseJSON['modules'];
            $responseData = array();
            foreach ($modulesArray as $eachmodule) {
                $module = $this->getZCRMModule($eachmodule);
                array_push($responseData, $module);
            }
            $responseInstance->setData($responseData);

            return $responseInstance;
        } catch (ZCRMException $exception) {
            APIExceptionHandler::logException($exception);
            throw $exception;
        }
    }
    
    /**
     * @param string $moduleName
     * @return APIResponse
     * @throws ZCRMException
     */
    public function getModule($moduleName) {
        try {
            $this->requestMethod = 'GET';
            $this->urlPath = "settings/modules/" . $moduleName;
            $this->addHeader("Content-Type", "application/json");
            $apiRequest = new APIRequest($this);
            $responseInstance = $apiRequest->getAPIResponse();
            $moduleArray = $responseInstance->getResponseJSON()['modules'];
            $responseInstance->setData($this->getZCRMModule($moduleArray[0]));
            return $responseInstance;
        } catch (ZCRMException $exception) {
            APIExceptionHandler::logException($exception);
            throw $exception;
        }
    }
    
    /**
     * @param array $moduleDetails
     * @return ZCRMModule
     */
    public function getZCRMModule($moduleDetails) {
        $crmModuleInstance = ZCRMModule::getInstance($moduleDetails['api_name']);
        $crmModuleInstance->setConfigs($this->configs);
        $crmModuleInstance->setApiKey($this->apiKey);
        $crmModuleInstance->setViewable($moduleDetails['viewable']);
        $crmModuleInstance->setCreatable($moduleDetails['creatable']);
        $crmModuleInstance->setConvertable($moduleDetails['convertable']);
        $crmModuleInstance->setEditable($moduleDetails['editable']);
        $crmModuleInstance->setDeletable($moduleDetails['deletable']);
        $crmModuleInstance->setWebLink(array_key_exists("web_link", $moduleDetails) ? $moduleDetails['web_link'] : null);
        $crmModuleInstance->setSingularLabel($moduleDetails['singular_label']);
        $crmModuleInstance->setPluralLabel($moduleDetails['plural_label']);
        $crmModuleInstance->setId($moduleDetails['id']);
        $crmModuleInstance->setModifiedTime($moduleDetails['modified_time']);
        $crmModuleInstance->setApiSupported($moduleDetails['api_supported']);
        $crmModuleInstance->setScoringSupported($moduleDetails['scoring_supported']);
        $crmModuleInstance->setModuleName($moduleDetails['module_name']);
        $crmModuleInstance->setBusinessCardFieldLimit(array_key_exists("business_card_field_limit", $moduleDetails) ? $moduleDetails['business_card_field_limit'] + 0 : 0);
        if (isset($moduleDetails['sequence_number'])) {
            $crmModuleInstance->setSequenceNumber($moduleDetails['sequence_number']);
        }

        if (isset($moduleDetails['global_search_supported'])) {
            $crmModuleInstance->setGlobalSearchSupported($moduleDetails['global_search_supported']);
        }

        $zcrmUserInstance = null;
        if ($moduleDetails['modified_by'] != null) {
            $zcrmUserInstance = ZCRMUser::getInstance(($moduleDetails['modified_by']["id"]), $moduleDetails['modified_by']["name"]);
        }
        $crmModuleInstance->setModifiedBy($zcrmUserInstance);
        $crmModuleInstance->setCustomModule('custom' === $moduleDetails['generated_type']);

        if (array_key_exists("business_card_fields", $moduleDetails)) {
            $crmModuleInstance->setBusinessCardFields($moduleDetails['business_card_fields']);
        }

        $profileArray = $moduleDetails['profiles'];
        $profileInstanceArray = array();
        foreach ($profileArray as $eachProfile) {
            array_push($profileInstanceArray, ZCRMProfile::getInstance($eachProfile['id'], $eachProfile['name']));
        }
        $crmModuleInstance->setAllProfiles($profileInstanceArray);

        if (array_key_exists("display_field", $moduleDetails)) {
            $crmModuleInstance->setDisplayFieldName($moduleDetails['display_field']);
        }
        $relatedListInstanceArray = null;
        if (array_key_exists("related_lists", $moduleDetails)) {
            $relatedListArray = $moduleDetails['related_lists'];
            $relatedListInstanceArray = array();
            foreach ($relatedListArray as $relatedListObj) {
                $moduleRelatedListIns = ZCRMModuleRelatedList::getInstance($relatedListObj['api_name']);
                array_push($relatedListInstanceArray, $moduleRelatedListIns->setRelatedListProperties($relatedListObj));
            }
        }
        $crmModuleInstance->setRelatedLists($relatedListInstanceArray);
        if (array_key_exists("layouts", $moduleDetails)) {
            $crmModuleInstance->setLayouts(ModuleAPIHandler::getInstance(ZCRMModule::getInstance($moduleDetails[APIConstants::API_NAME]))->getLayouts($moduleDetails['layouts']));
        }

        if (array_key_exists("fields", $moduleDetails) && $moduleDetails['fields'] != null) {
            $crmModuleInstance->setFields(ModuleAPIHandler::getInstance(ZCRMModule::getInstance($moduleDetails[APIConstants::API_NAME]))->getFields($moduleDetails['fields']));
        }

        if (array_key_exists("related_list_properties", $moduleDetails) && $moduleDetails['related_list_properties'] != null) {
            $crmModuleInstance->setRelatedListProperties(self::getRelatedListProperties($moduleDetails['related_list_properties']));
        }

        if (array_key_exists('$properties', $moduleDetails) && $moduleDetails['$properties'] != null) {
            $crmModuleInstance->setProperties($moduleDetails['$properties']);
        }

        if (array_key_exists('per_page', $moduleDetails) && $moduleDetails['per_page'] != null) {
            $crmModuleInstance->setPerPage($moduleDetails['per_page'] + 0);
        }

        if (array_key_exists('search_layout_fields', $moduleDetails) && $moduleDetails['search_layout_fields'] != null) {
            $crmModuleInstance->setSearchLayoutFields($moduleDetails['search_layout_fields']);
        }
        if (array_key_exists('custom_view', $moduleDetails) && $moduleDetails['custom_view'] != null) {
            $crmModuleInstance->setDefaultCustomView(self::getModuleDefaultCustomView($moduleDetails[APIConstants::API_NAME], $moduleDetails['custom_view']));
            $crmModuleInstance->setDefaultCustomViewId($moduleDetails['custom_view']['id']);
        }
        if (array_key_exists('territory', $moduleDetails) && $moduleDetails['territory'] != null) {
            $crmModuleInstance->setDefaultTerritoryId($moduleDetails['territory']['id']);
            $crmModuleInstance->setDefaultTerritoryName($moduleDetails['territory']['name']);
        }

        return $crmModuleInstance;
    }

    public function getModuleDefaultCustomView($moduleAPIName, $customViewDetails) {
        $customViewInstance = ZCRMCustomView::getInstance($moduleAPIName, $customViewDetails['id']);
        $customViewInstance->setDisplayValue($customViewDetails['display_value']);
        $customViewInstance->setDefault((boolean) $customViewDetails['default']);
        $customViewInstance->setName($customViewDetails['name']);
        $customViewInstance->setSystemName($customViewDetails['system_name']);
        $customViewInstance->setSortBy(array_key_exists('sort_by', $customViewDetails) ? $customViewDetails['sort_by'] : null);
        $customViewInstance->setCategory(array_key_exists('category', $customViewDetails) ? $customViewDetails['category'] : null);
        $customViewInstance->setFields(array_key_exists('fields', $customViewDetails) ? $customViewDetails['fields'] : null);
        $customViewInstance->setFavorite($customViewDetails['favorite']);
        $customViewInstance->setSortOrder(array_key_exists('sort_order', $customViewDetails) ? $customViewDetails['sort_order'] : null);
        if (array_key_exists('criteria', $customViewDetails) && $customViewDetails['criteria'] != null) {
            $criteriaList = $customViewDetails['criteria'];
            $criteriaPattern = "";
            $criteriaInstanceArray = array();
            if (isset($criteriaList[0]) && is_array($criteriaList[0])) {
                for ($i = 0; $i < sizeof($criteriaList); $i++) {
                    $criteria = array_values($criteriaList)[$i];
                    if ($criteria === "or" || $criteria === "and") {
                        $criteriaPattern = $criteriaPattern . $criteria;
                    } else {
                        $criteriaInstance = ZCRMCustomViewCriteria::getInstance();
                        $criteriaInstance->setField($criteria['field']);
                        $criteriaInstance->setValue($criteria['value']);
                        $criteriaInstance->setComparator($criteria['comparator']);
                        $criteriaPattern = $criteriaPattern . $i;
                        array_push($criteriaInstanceArray, $criteriaInstance);
                    }
                }
            } else {
                $criteriaInstance = ZCRMCustomViewCriteria::getInstance();
                $criteriaInstance->setField($criteriaList['field']);
                $criteriaInstance->setValue($criteriaList['value']);
                $criteriaInstance->setComparator($criteriaList['comparator']);
                array_push($criteriaInstanceArray, $criteriaInstance);
            }
            $customViewInstance->setCriteria($criteriaInstanceArray);
            $customViewInstance->setCriteriaPattern($criteriaPattern);
        }

        if (isset($customViewDetails['offline'])) {
            $customViewInstance->setOffLine($customViewDetails['offline']);
        }

        return $customViewInstance;
    }

    public function getRelatedListProperties($relatedListProperties) {
        $relatedListPropInstance = ZCRMRelatedListProperties::getInstance();
        $relatedListPropInstance->setSortBy(array_key_exists("sort_by", $relatedListProperties) ? $relatedListProperties['sort_by'] : null);
        $relatedListPropInstance->setSortOrder(array_key_exists("sort_order", $relatedListProperties) ? $relatedListProperties['sort_order'] : null);
        $relatedListPropInstance->setFields(array_key_exists("fields", $relatedListProperties) ? $relatedListProperties['fields'] : null);

        return $relatedListPropInstance;
    }

}