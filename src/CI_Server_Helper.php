<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CI Server Helper
 * 
 * Provide request() and response() function, response() work like Laravel's response()
 * 
 * Author: Fatih Aziz
 * date: 14 Jan 2020
 * last update: 18 Feb 2020
 * repo: https://github.com/fatih-aziz/php-library
 * licence: GNU General Public License v3.0
 * P.S: "just do whatever you want with this code but please put my repo link inside your codes. thanks"
 */


if(!class_exists('ciResponse') && !function_exists('response')){
	/**
	 * CI Responder
	 * 
	 * Helper for codeigniter create auto response
	 * 
	 */

	class ciResponse{
		protected $content;
		protected $status;
		protected $header;
		private static $instance;

		function __construct($options=[])
		{
			foreach($options as $key=>$opt)
			{
				$this->$key = $opt;
			}
		}

		public static function _()
		{
		  if ( is_null( self::$instance ) )
		  {
			self::$instance = new self();
		  }
		  return self::$instance;
		}

		private function resp($content,$status)
		{
			if(@!$this->not_json)
				header('Content-Type: application/json');
			
			http_response_code($status);
			echo json_encode($content);
			
			if(@!$this->sync)
				die();
		}

		function make($content='',$status=200,array $header=[])
		{
			if(is_array($content) && !is_object($content))
				$content = arr2Obj($content);

			// auto response
			if(!empty($content) && !empty($content->error))
			{
				$status = !empty($content->error->statusCode)? $content->error->statusCode: 400;
				$this->error($content,$status,$header);
			}
			else if(!empty($content) && isJson($content))
				$this->success($content,$status,$header);
			else if(!empty($content))
				$this->success($content,$status);
			else
				$this->success(["data"=>"no data"],$status);
		}

		/**
		 * create success response.
		 *
		 * @param string $content
		 * @param integer $status
		 * @param array $header
		 * @return void
		 */

		function success($content='',$status=200,array $header=[]){
			if(empty($content))
				$this->success(["data"=>"no data"],$status!=200?$status:204);
			$this->resp($content,$status);
		}

		function errorRaw($content='',$status=400,array $header=[]){
			$this->resp($content,$status);
		}

		/**
		 * create error response.
		 * can use array or object, or just make your own error result, or modify default format
		 * [0]=>statusCode, [1]=>code, [2]=>Name, [3]=>Message, [4]=>link
		 *
		 * @param string $content
		 * @param integer $status
		 * @param array $header
		 * @return void
		 */
		function error($content='',$status=400,array $header=[]){
			$errFormat = [
				'statusCode'	=> $status,
				'code'			=> 0,
				'name'			=> "general",
				'message'		=> "System Error",
				'link'			=> "",
			];
			
			if(empty($content)){
				$content 					= [];
				$errFormat['statusCode'] 	= 400;
				$errFormat['name'] 			= "no_data";
				$errFormat['message'] 		= "No Data";
			}
			// create error response. [0]=>statusCode, [1]=>code, [2]=>Name, [3]=>Message, [4]=>link
			else if(is_array($content) || is_object($content))
			{
				$content = obj2Arr($content);
				foreach($content as $k=>$v)
				{
					if(array_keys($errFormat)[$k])
						$errFormat[array_keys($errFormat)[$k]] = $v;
					else if($k == "error_description")
					{
						$errFormat[$k] = $v;
						$errFormat['message'] = $v;
					}
					else
						$errFormat[$k] = $v;
					unset($content[$k]);
				}
			}

			else if(!empty($content->error))
			{
				if(!is_array($content->error) && !is_object($content->error))
				{
					$errFormat['name']	= implode("_",explode(" ",strtolower($content->error)));
					$errFormat['message']	= $content->error;
				}
				else if(is_array($content->error) || is_object($content->error))
				{
					$error = obj2Arr($content->error);
					foreach($error as $k=>$v)
					{
						if(isset($errFormat[$k]))
							$errFormat[$k]=$v;
					}
				}
			}

			else if(!empty($content))
			{
				$errFormat['name']	= implode("_",explode(" ",strtolower($content)));
				$errFormat['message']	= $content;
			}

			if(is_array($content) || is_object($content))
				$content = arr2Obj(array_merge(obj2Arr($content),obj2Arr($errFormat)));
			else
				$content = $errFormat;
			$this->resp($content,$content->statusCode?:$status);
		}
		/**
		 * return unauthorize error response
		 *
		 * @return void
		 */
		function unAuth()
		{
			$this->error(['401','401','UNAUTHORIZE','Unauthorize','']);
		}

		/**
		 * return unauthorize error then redirect it to login
		 *
		 * @return void
		 */
		function unAuthRedirect($redirect = "")
		{
			$this->sync = true;
			$this->error(['401','401','UNAUTHORIZE','Unauthorize','']);
			redirect(site_url($redirect?:'/'), 'refresh');
		}

		/**
		 * initialize ci response
		 *
		 * @param array $asd
		 * @return void
		 */
		function init($options)
		{
			return new ciResponse($options);
		}

	}
	/**
	 * create simple response and stop the php after response.
	 *
	 * @param string $result
	 * @param integer $status
	 * @param array $header
	 * @return void
	 */
	function response($content = '',$status=200, $header=[]){
		$status = $status?:200;
		$header = $header?:[];
		
		$resp = new ciResponse();
		if(func_num_args() == 0)
			return $resp;
		return $resp->make($content,$status,$header);
	}
}

if(!class_exists('ciRequest') && !function_exists('request')){
	/**
	 * CI Request
	 * 
	 * Helper for simpler codeigniter request
	 * 
	 */
	class ciRequest{
		protected $req;
		function __construct($opts=[])
		{
			$ci =& get_instance();
			$this->req = $ci->input;
			foreach($opts as $key=>$opt)
			{
				$this->$key = $opt;
			}
			return $this->req;
		}

		function all($opts=[])
		{
			$get = $this->req->get();
			$post = $this->req->post();
			$headers = [];
			
			if(!empty($opts['with_header']))
				$headers = $this->headers();
			return array_merge($get,$post,$headers);
		}

        function get($arr)
        {
            return $this->req->get($arr);
        }

		/**
		 * Check request header is want a json response.
		 *
		 * @return boolean
		 */

		function wantJson()
		{
			return !empty($this->req->get_request_header('Accept')) && $this->req->get_request_header('Accept') === "application/json";
		}

		/**
		 * function that help for get token from header Auth or access-token param
		 *
		 * @return string
		 */
		function getBearerToken()
		{
			if(!empty($this->req->get_request_header('Authorization')))
			{
				$auth = $this->req->get_request_header('Authorization');
				if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
					return $matches[1];
				};
				return false;
			}
		}

		/**
		 * return value of spesific request header
		 *
		 * @param string $heads
		 * @return array
		 */
		function headers($heads='')
		{
			if(!empty($heads))
				return $this->req->get_request_header($heads);
			else
				return $this->req->request_headers();
		}
	}

	/**
	 * Extending $this->input with some help functions
	 *
	 * @param object $request *place $this->input here
	 * @return void
	 */
	function request(){
		return new ciRequest();
	}
}

//  Require Arr2OBJ function
if(!function_exists('obj2Arr')){
	/**
     * Array to Object - Just like the name, converting php array to object
     *
     * @param object $object
     * @return array
     */
	function obj2Arr($object)
	{
		return json_decode(json_encode($object), true);
	}
}

//  Require Arr2OBJ function
if(!function_exists('arr2Obj')){
	/**
     * Array to Object - Just like the name, converting php array to object
     *
     * @param array $arr
     * @return object
     */
	function arr2Obj($arr)
	{
		return json_decode(json_encode($arr));
	}
}

//  Require isJson function
if(!function_exists('isJson')){
	/**
     * Is this string a valid json?
     *
     * @param array $str
     * @return boolean
     */
	function isJson($content)
	{
		if(is_array($content) || is_object($content))
			return true;
		$obj = json_decode($content);
		return (json_last_error() == JSON_ERROR_NONE && !empty($obj));
    }
}
// Check function requirement, will pass if used in Codeigniter
if(!function_exists('get_instance')){
    function get_instance()
    {
        throw new Error("get_intance_not_found");
    }
}
if(!function_exists('redirect')){
    function redirect()
    {
        throw new Error("redirect_not_found");
    }
}
if(!function_exists('site_url')){
    function site_url()
    {
        throw new Error("site_url_not_found");
    }
}
