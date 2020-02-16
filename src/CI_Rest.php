<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Psr\Http\Message\RequestInterface;
use \GuzzleHttp\HandlerStack;
use \GuzzleHttp\Middleware;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception as RestException;

/**
 * CI Rest
 * 
 * Extend Guzzle with more simpler and easy to use
 * 
 * Author: Fatih Aziz
 * date: 14 Jan 2020
 * last update: 21 Jan 2020
 * repo: https://github.com/fatih-aziz/php-library
 * licence: GNU General Public License v3.0
 */

class Rest extends Client{
    private static $instance;

	function __construct($opts=[])
	{
        $ci =& get_instance();
        $config = $_SERVER[getenv('APP_ENV')]['api'];
		$defaults = [
			'verify' => false,
            'base_uri'=> $config['url'],
            'headers'=>[
                'Accept' => 'application/json',
				'Content-type' => 'application/json',
				'X-API-KEY' => $config['key']
			],
			'timeout' => 5,
			'connect_timeout' => 5
		];

		$opts = array_merge($defaults,$opts);
		parent::__construct($opts);
	}


    public static function _()
    {
      if ( is_null( self::$instance ) )
      {
        self::$instance = new self();
      }
      return self::$instance;
    }

	private function getParamUrl($url,$param)
	{
		if($urlSchema = parse_url(urldecode($url)))
		{
			parse_str(@$urlSchema['query'],$query);
			$param = array_merge($param,@$query);
		}
		return $param;
	}

    public function simpleGet($url,$param=[])
    {
		try{
			$param = $this->getParamUrl($url,$param);
			$resp = parent::get($url,['query'=>$param])->getBody();
		}
		catch(RestException\BadResponseException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\RequestException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\ConnectException $e)
		{
			$resp = $this->ex($e);
		}
			return json_decode($resp);
	}
		
		
    public function simplePost($url,$data=[],$param=[])
    {
		try{
			$param = $this->getParamUrl($url,$param);
			$resp = parent::post($url,['form_params'=>$data,'query'=>$param])->getBody();
		}
		catch(RestException\BadResponseException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\RequestException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\ConnectException $e)
		{
			$resp = $this->ex($e);
		}
			return json_decode($resp);
	}
	
	public function simplePut($url,$data=[],$param=[])
	{
		try{
			$param = $this->getParamUrl($url,$param);
			$resp = parent::put($url,['form_params'=>$data,'query'=>$param])->getBody();
		}
		catch(RestException\BadResponseException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\RequestException $e){
			$resp = $this->ex($e);
		}
		catch(RestException\ConnectException $e)
		{
			$resp = $this->ex($e);
		}
			return json_decode($resp);
	}

	/**
	 * Handle all exception
	 *
	 * @param object $e
	 * @return void
	 */
    private function ex($e)
    {
		if(!empty($e->hasResponse()))
		{
			return json_encode(['error'=>$e->getResponse()->getBody()->getContents()]);
		}
		else
			return json_encode(['error'=>$e->getMessage()]);
	}
}
