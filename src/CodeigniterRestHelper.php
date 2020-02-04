<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CI Server Helper
 * 
 * Provide request() and response() function, response() work like Laravel's response()
 * 
 * Author: Fatih Aziz
 * date: 14 Jan 2020
 * last update: 21 Jan 2020
 * repo: https://github.com/fatih-aziz/php-library
 * licence: GNU General Public License v3.0
 */

if(!class_exists('ciResponse') && !function_exists('response'))
{
	/**
	 * ciResponse Class here will generate Json Response
	 * 
	 */

	class ciResponse
	{
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
			$this->resp($content,$status);
		}

		function errorRaw($content='',$status=400,array $header=[]){
			$this->resp($content,$status);
		}

		/**
		 * create error response. [0]=>statusCode, [1]=>code, [2]=>Name, [3]=>Message, [4]=>link
		 *
		 * @param string $content
		 * @param integer $status
		 * @param array $header
		 * @return void
		 */
		function error($content='',$status=400,array $header=[]){
			if(empty($content))
				$this->error(['400','','NO DATA','No Data'],$status?: 400);
			if(empty($content->error) && is_array($content) && !is_object($content))
			{
				$err = ['statusCode','code','name','message','link'];
				$errPool=[];
				foreach($content as $k=>$v)
				{
					$errPool[$err[$k]]=$v;
				}
				$content = arr2Obj(['error'=>$errPool]);
			}
			$this->resp($content,$status);
		}
		/**
		 * return unauthorize error response
		 *
		 * @return void
		 */
		function unAuth()
		{
			$this->error(['400','400','UNAUTHORIZE','Unauthorize','']);
		}
		
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
	function response($content = '',$status=200, $header=[])
	{
		$status = $status?:200;
		$header = $header?:[];
		
		$resp = new ciResponse();
		if(func_num_args() == 0)
			return $resp;
		return $resp->make($content,$status,$header);
	}
}

if(!class_exists('ciRequest') && !function_exists('request'))
{
	/**
	 * CI Request
	 * 
	 * Helper for simpler codeigniter request
	 * 
	 * Author: Fatih Aziz
	 * Date: Nov 2019
	 */
	class ciRequest
	{
		protected $req;
		function __construct()
		{
			$ci =& get_instance();
			$this->req = $ci->input;
			return $this->req;
		}

		function all()
		{
			$get = $this->req->get();
			$post = $this->req->post();
			return array_merge($get,$post);
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
		function getToken()
		{
			if(!empty($this->req->get_request_header('Authorization')))
				return $this->req->get_request_header('Authorization');
			if(!empty($this->req->get('access-token')))
				return $this->req->get('access-token');
			if(!empty($this->req->post('access-token')))
				return $this->req->post('access-token');
		}

		/**
		 * return value of spesific request header
		 *
		 * @param string $heads
		 * @return string
		 */
		function headers($heads='')
		{
			if(!empty($heads))
				return $this->req->get_request_header($heads);
			else
				return $this->req->get_headers();
		}
	}

	/**
	 * Extending $this->input with some help functions
	 *
	 * @param object $request *place $this->input here
	 * @return void
	 */
	function request()
	{
		return new ciRequest();
	}
}

//  Require Arr2OBJ function
if(!function_exists('arr2Obj'))
{
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
if(!function_exists('isJson'))
{
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