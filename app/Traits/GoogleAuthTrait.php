<?php
    namespace App\Traits;

    use Illuminate\Http\Request;

    /**
     * 
     */
    trait GoogleAuthTrait
    {
        private $client;
        public $availableScopes = array(
            'readOnly' => 'https://www.googleapis.com/auth/youtube.readonly',
            'manageAcc' => 'https://www.googleapis.com/auth/youtube',
            'editAcc' => 'https://www.googleapis.com/auth/youtube.force-ssl',
            'upload' => 'https://www.googleapis.com/auth/youtube.upload'
        );

        public function initAuth($getUrl = false, $scopes = array()){
            $client = $returnVal = null;

            if(count($scopes) == 0) $scopes[] = $this->availableScopes['readOnly'];
        
            try{
                //Create and Request to access Google API
                $client = new \Google_Client();
                $client->setApplicationName(env('APP_NAME', 'Youtumen'));
                $client->setClientId(env('GOOGLE_CLIENT_ID'));
                $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
                $client->setAccessType("offline");        // offline access
                $client->setIncludeGrantedScopes(true);   // incremental auth
                $client->setScopes($scopes);  //user profile , email scope
                if(env('GOOGLE_DEVELOPER_KEY', null)) $client->setDeveloperKey(env('GOOGLE_DEVELOPER_KEY'));
                $client->setRedirectUri(env('GOOGLE_REDIRECT_URL'));
                if(is_object($client) && in_array('createAuthUrl', get_class_methods($client))){
                    $returnVal = $client->createAuthUrl();
                    if(empty($returnVal) == false) $returnVal = filter_var($returnVal, FILTER_SANITIZE_URL);
                }
            }catch(Exception $e){
                
            }
            if($getUrl == false && $client) $returnVal = $client;
            return $returnVal;
        }
    }
    
?>