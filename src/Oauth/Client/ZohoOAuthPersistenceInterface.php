<?php

namespace Zoho\Oauth\Client;

interface ZohoOAuthPersistenceInterface {
    
    /**
     * Save oAuth data
     * 
     * @param type $zohoOAuthTokens
     */
    public function saveOAuthData($zohoOAuthTokens);
    
    /**
     * Get oAuth tokens
     * 
     * @param mixed $userEmailId
     */
    public function getOAuthTokens($userEmailId);
    
    /**
     * Delete oAuth tokens
     * 
     * @param type $userEmailId
     */
    public function deleteOAuthTokens($userEmailId);
    
    /**
     * Get grant token
     */
    public function getGrantToken();
    
}
