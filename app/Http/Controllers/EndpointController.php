<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class EndpointController extends Controller
{
    private $requestObj;
    private $configObj;
    private $currentAction = null;
    private $currentEndpoint = null;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->middleware('jwt.auth');

        if($request) $this->requestObj = $request;

        //Set configuration
        $this->setConfiguration();
    }

    public function setConfiguration(){
        $configFile = str_replace('\\', '/', base_path()).'/config/endpoints.json';

        $jsonContent = "";
        $jsonContent = file_get_contents($configFile);

        if(empty($jsonContent) == false){
            $tmpContentArr = json_decode($jsonContent, true);

            //Set global configured variables
            if(count($tmpContentArr['Global']) > 0) $this->configObj['Global'] = $tmpContentArr['Global'];

            //Set endpoint related variables
            if(count($tmpContentArr['Endpoints'] > 0)){
                //All availabele endpoints
                $this->configObj['AllowedEndpoints'] = array_keys($tmpContentArr['Endpoints']);
                $this->configObj['EndpointConfig'] = $tmpContentArr['Endpoints'];
            }
        }
    }

    public function checkAction(&$action){
        $returnVal = false;

        if($action || in_array($action, $this->configObj['AllowedEndpoints'])){
            $this->currentAction = trim($action);
            $this->currentEndpoint = $this->configObj['EndpointConfig'][$this->currentAction]['endpoint'];
            $returnVal = true;
        }

        if(!$this->currentEndpoint) $returnVal = false;

        return $returnVal;
    }

    public function checkParameters(){
        $returnArr = array('Status' => true, 'msg' => array());

        $receivedParams = $this->requestObj->all();
        $allowedParams = array_keys($this->configObj['EndpointConfig'][$this->currentAction]['params']);

        if(count($receivedParams) > 0){
            foreach($receivedParams as $reqVars){
                if(!in_array($reqVars, $allowedParams)) $returnArr['msg'][] = $reqVars;
            }
        }

        if(count($returnArr['msg']) > 0){
            $returnArr['Status'] = false;
            $returnArr['msg'] = implode(', ', array_unique($returnArr['msg']));
        }

        return $returnArr;
    }

    /**
     * All the actions will be validated and then will be executed
     */
    public function performAction($action = null){
        //Check if current action is allowed or not
        if(!$this->checkAction($action)){
            return response()->json([
                'error' => 'Invalid action provided.'
            ], 400);
        }

        //Check if request parameters are also allowed or not
        $validParams = $this->checkParameters();
        if(!$validParams['Status'] || $validParams['msg']){
            return response()->json([
                'error' => 'Following parameters are not allowed. Invalid parameters: '.$validParams['msg']
            ], 400);
        }

        try{
            //Prepare cURL client with configuration
            $client = new Client([
                'base_uri' => $this->configObj['Global']['url']
            ]);

            // Send a request
            $response = $client->request('GET', $this->currentEndpoint, [
                'query' => ['key' => env('YOUTUBE_KEY'), 'part' => 'snippet,contentDetails'],
                'headers' => ['Referer' => env('APP_URL'), 'Accept'     => 'application/json']
            ]);
        }catch(ClientException $e){
            $error = [
                'status' => 400,
                'message' => 'Error Ocurred !'
            ];
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();

            if(empty($responseBodyAsString) == false){
                $tmpStr = json_decode($responseBodyAsString, true);
                if($tmpStr['error']['code']) $error['status'] = $tmpStr['error']['code'];
                if($tmpStr['error']['message']) $error['message'] = $tmpStr['error']['message'];
            }
            
            return response()->json([
                'error' => $error['message']
            ], $error['status']);
        }
    }
}
