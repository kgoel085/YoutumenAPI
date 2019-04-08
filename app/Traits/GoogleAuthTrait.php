<?php
    namespace App\Traits;

    use Illuminate\Http\Request;
    use App\Http\Controllers\JWTController;
    use App\UserGoogleToken;

    /**
     * 
     */
    trait GoogleAuthTrait
    {
        private $client;
        public $availableScopes = array(
            'readOnly' => array('https://www.googleapis.com/auth/youtube.readonly', 'profile', 'email'),
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

        //Generates the oAuth url from google
        public function generateUrl(){
            $defaultScope = array_search('https://www.googleapis.com/auth/youtube.readonly', $this->availableScopes);

            $urlLink = $this->initAuth(true);

            if($urlLink){
                return response()->json([
                    'success' => $urlLink
                ], 200);
            }
        }

        //Update the JWT token with the authorized access token
        public function registerToken(Request $request){
            $token = $authToken = $userObj = null;
            if($request->token) $token = trim($request->token);

            if(!$token){
                return response()->json([
                    'error' => 'Token not provided.'
                ], 400);
            }

            //Creates Google auth object from Triat
            $gClient = $this->initAuth();

            //If google_token is present then return that back
            if(array_key_exists('google_token', $request->jwt)){
                return response()->json([
                    'error' => 'Authorization already done'
                ], 400);
            }

            //Valdiate the received code
            $authToken = $gClient->fetchAccessTokenWithAuthCode($token);

            if(!$authToken || (is_array($authToken) && array_key_exists('error', $authToken))){
                return response()->json([
                    'error' => 'Google authentication failed. Please try again. '.$authToken['error_description']
                ], 400);
            }

            //Set the authorize code
            if($authToken){
                //Save record in DB with unique token for server
                $newToken = sha1(time());

                $userToken = UserGoogleToken::where([['user_id', '=', $request->jwt->sub], ['token', '=', $newToken]])->first();
                if(!$userToken){
                    $userToken = UserGoogleToken::create([
                        'user_id' => $request->jwt->sub,
                        'token' => $newToken,
                        'g_auth_token' => json_encode($authToken)
                    ]);
                }

                $newToken = $userToken->token;

                //Generate new JWT token with google user details
                $jwtObj = new JWTController($request);
                $response = $jwtObj->generateToken(array('authToken' => $newToken));

                return $response;
            }
        }
    }
    
?>