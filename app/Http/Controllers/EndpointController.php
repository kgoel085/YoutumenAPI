<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EndpointController extends Controller
{
    private $requestObj;
    private $configObj;
    private $currentAction = null;

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

    /**
     * All the rquests will be validated and then passed on 
     */
    public function getAction($action = null){
        //Check if current action is allowed or not
        if(!$this->checkAction($action)){
            return response()->json([
                'error' => 'Invalid action provided.'
            ], 400);
        }

        //Check if request parameters are also allowed or not
        $validParams = $this->checkParameters();
        if(!$validParams['Status']){
            return response()->json([
                'error' => 'Following parameters are not allowed. Invalid parameters: '
            ], 400);
        }


        
    }

    public function checkAction(&$action){
        $returnVal = false;

        if($action || in_array($action, $this->configObj['AllowedEndpoints'])){
            $this->currentAction = trim($action);
            $returnVal = true;
        }

        return $returnVal;
    }


}
