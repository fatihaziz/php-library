<?php
defined('APP_ROOT') OR exit('No direct script access allowed');

use Carbon\Carbon;

/**
 * CI Model ORM & Security Controller
 * 
 * if you familier with laravel orm / query builder, this will really helpfull. 
 * 
 * How to Use:
 * - require_once this file
 * - inside CI Model Class:
 * -- Use CrudDB;
 * - now you can call all the method inside model, example $this->find_one($id);
 * - inside CI Controller Class:
 * -- use SecureController;
 * 
 * Author: Fatih Aziz
 * date: 18 Feb 2020
 * last update: 21 Jan 2020
 * repo: https://github.com/fatih-aziz/php-library
 * licence: GNU General Public License v3.0
 */

trait SecureController{
    protected $token;
    protected $access_token;

    private function middleware($methodArr,$callback){
        if(is_array($methodArr))
        foreach($methodArr as $method)
        {
            if(is_callable($callback))
            {
                $method = trim($method," ");
                if(strpos($method,"!") !== false){
                    if(trim($method,"!") != $this->router->method)
                        $callback($method);
                }
                else if($method === $this->router->method)
                    $callback($method);
            }
        }
    }

    private function check_security($scope="")
    {
        $scope = strtolower($scope);
        $this->token = $this->session->userdata('token');
        $this->access_token = $this->session->userdata('access_token');
        if($this->access_token)
            $token = $this->token_model->decode($this->access_token);
        else if($this->access_token)
            $token = $this->token_model->decode($this->token->access_token);
        else{
            $token = request()->getBearerToken();
            $token = $this->token_model->decode($token);
            $this->token        = arr2Obj(['access_token'=>$token]);
            $this->access_token = $token;
        }
        if(!empty($scope) && $token->info->scope == $scope)
            return true;
        if(!empty($token) && empty($token->error))
            return true;
        session_destroy();
        if(request()->wantJson)
            return response()->unauth();
        else
            return response()->unAuthRedirect();
    }
}

trait CrudDB {
    protected $crudDB;

    // QUERY SECTION

    /**
     * Handle ORM Exceptions
     *
     * @param string $e
     * @param array $opts
     * @return object
     */
    protected function errorHandling($e="",$opts=[]){
        if($opts['debug'] === true)
            throw $e;
        
        if(!empty($e->getMessage())){
            $e['code'] = $e->getCode();
            $e['message'] = $e->getMessage();
        }
        else {
            $e  = $this->db->error();
        }
        $e  = arr2Obj($e);

        // handle duplicate entry,
        if($e->code == 1062){
            $e->code    = 101062;
            $e->message = strLimit($e->message,5);
        }
        
        if($opts['name']){
           $e->name     = $opts['name'];
        }
        
        return arr2Obj(['error'=>$e]);
    }

    /**
     * Matching input data with database fields, if not match, throw them out.
     *
     * @param [type] $data
     * @param array $field_filter
     * @param array $opts
     * @return object
     */
    private function matchingDataDB($data,$filter=[],$opts=[]){
        try{
            
            if(!is_array($data))
                throw new Exception('data is not array');
            $fields     = $this->filterFieldsDB(array_merge($filter,array_keys($data)),[],['escape'=>true]);
            
            $accepted_data=[];
            foreach($fields as $f){
                if(!empty($data[$f]) || is_numeric($data[$f]))
                    $accepted_data[$f] = $data[$f];
            }
                        
            return $accepted_data;
        }catch(Exception $e){
            throw new Exception("CrudDB-Error[match]: ".$e);
        }
    }

    /**
     * Determining which allowed fields to show.
     *
     * @param array $filters
     * @param array $fields
     * @param array $opts
     * @return array
     */
    private function filterFieldsDB($filters=[],$fields=[],$opts=[]){
        try{
            $table          = $this->crudDB->name;

            if(empty($fields))
                $fields     = $this->db->list_fields($this->crudDB->name);
            if(empty($filters) && !empty($this->crudDB->$table->fields))
                $filters        = $this->crudDB->$table->fields;
            else if(empty($filters))
                $filters        = $fields;
            
            $filter_field   = [];
            $filter_field   = array_map(function($v) use (&$filters,$opts){
                $v      = trim($v," ");
                $field  = $v;
                
                $allow          = false;
                foreach($filters as $filter){
                    $filter = trim($filter," ");
                    $escapefilter   = null;
                    
                    // escape db operators
                    if($opts['escape']){
                        $escapefilter   = $filter;
                        $explode    = explode(" ",$filter);
                        $filter     = $explode[0];
                    }

                    if(strpos($filter,"!") !== false){
                        if(trim($filter,"!") != $v){
                            $allow = true;
                            if(!empty($explode[1])){
                                $field  = $escapefilter;
                            }
                        }
                        else if($filter == $v){
                            $allow = true;
                            if(!empty($explode[1])){
                                $field  = $escapefilter;
                            }
                        }
                        else
                        {
                            $allow = false;
                            break;
                        }
                    }
                    else if($filter == $v){
                        $allow = true;
                        if(!empty($explode[1])){
                            $field  = $escapefilter;
                        }
                    }
                }
            
                if($allow === true)
                    return $field;
            },$fields);
            $filter_field = array_filter($filter_field);
            return array_values($filter_field);
        }catch(Exception $e){
            throw new Exception("CrudDB-Error[filter]: ".$e);
        }
    }

    /**
     * Set which fields/coloumn will selected in db
     *
     * @param array $fields
     * @param array $opts
     * @return array
     */
    private function selectFieldsDB($fields=[],$opts=[]){
        try{
            $table  = $this->crudDB->name;
            if(!empty($fields)){
                $filtered_fields                =   $this->filterFieldsDB($fields);
                $this->crudDB->$table->fields   = $filtered_fields;
            }
            
            else if(!empty($opts)){
                if(($opts === true || $opts['reset']==true)){
                    $filtered_fields                =   $this->filterFieldsDB($this->db->list_fields($table));
                    $this->crudDB->$table->fields   = $filtered_fields;
                }
            }

            else if(empty($fields) && empty($opts)){
                if(!empty($this->crudDB->$table->fields)){
                    $filtered_fields                = $this->filterFieldsDB($this->crudDB->$table->fields);
                    $this->crudDB->$table->fields   = $filtered_fields;
                }else{
                    $filtered_fields                = $this->filterFieldsDB();
                    $this->crudDB->$table->fields   = $filtered_fields;
                }
            }
            
            return $filtered_fields;
        }catch(Exception $e){
            throw new Exception("CrudDB-Error[selectfield]: ".$e);
        }
    }

    /**
     * Set from talbe
     *
     * @param string $table
     * @param array $opts
     * @return string
     */
    private function tableNameDB($table="",$opts=[]){
        try{
            if(!empty($table)){
                $this->crudDB->old_name = $this->crudDB->name;
                $this->crudDB->name = $table;
            }
            else if(!empty($opts)){
                if(($opts === true || $opts['reset']==true) && !empty($this->crudDB->old_name)){
                    $this->crudDB->name = $this->crudDB->old_name;
                    unset($this->crudDB->old_name);
                }
            }
            else
                throw new Error("Table not found");
            return $this->crudDB->name;
        }catch(Exception $e){
            throw new Exception("CrudDB-Error[tablename]: ".$e);
        }
    }

    /**
     * Reset all previous set selected and table name to model default
     *
     * @return void
     */
    private function resetNameDB(){
        $this->tableNameDB(null,true);
        $this->selectFieldsDB(null,true);
    }

    /**
     * Extend join query
     *
     * @param [type] $table
     * @param array $keys
     * @return object
     */
    private function joinDB($table,array $keys){
        try{
            $selectMainDB   = $this->selectFieldsDB();
            $selectJoinDB   = $this->db->list_fields($table);
            $key_query      = array_map(function($key) use (&$table,$keys) {
                return $this->crudDB->name . '.' . $key . ' = ' . $table . '.' .$keys[$key];
            },array_keys($keys));
            $select_query_main  = array_map(function($q) {
                return $this->crudDB->name.'.'.$q;
            },(array)$selectMainDB);
            $select_query_join  = array_map(function($q) use(&$table) {
                return $table.'.'.$q;
            },(array)$selectJoinDB);
            $key_query  = implode(", ",$key_query);
            $select         = array_merge($select_query_join,$select_query_main);
            $join_query     = $this->db->select(implode(", ",$select));
            $join_query     = $join_query->from($this->crudDB->name,'LEFT');
            $join_query     = $join_query->join($table,$key_query,'LEFT');
            return $join_query;
        }catch(Exception $e){
            throw new Exception("CrudDB-Error[joinDB]: ".$e);
        }
    }


    // GET RESULT FROM QUERY SECTION

    /**
     * save data into db
     *
     * @param [type] $data
     * @return object
     */
	private function saveDB($data,$opts=[]){
		try{
            $data = $this->matchingDataDB($data);
			if(!$this->db->insert($this->crudDB->name,$data))
				throw new Exception();
			$data['id'] = $this->db->insert_id();
			$this->resetNameDB();
            return arr2Obj($data);
		}
		catch(Exception $e)
		{
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_create_fail'],$opts));
			return $err;
		}
	}

    /**
     * get data from db
     *
     * @param string $id
     * @param array $search
     * @param array $opts
     * @return object
     */
	private function getDB($id="",$where=[],$opts=[]){
		try{
            if(!empty($id))
                $where['id'] = $id;
            $select = $this->selectFieldsDB();
            
            if(!empty($where))
                $where  = $this->matchingDataDB($where);
            $query  = $this->db->select(implode(", ",$select));
            if(!$get = $query->get_where($this->crudDB->name,$where))
                throw new Exception();
            $this->resetNameDB();
			return $get->result();
        }
		catch(Exception $e)
		{
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_get_fail'],$opts));
			return $err;
        }
    }
    
    private function joinGetDB($table,array $keys,$where=[],$opts=[]){
        try{
            $join_query     = $this->joinDB($table,$keys);
            
            $where_filtered = $this->filterFieldsDB(array_keys((array)$where));
            // reformat where clause
            $where_format   = [];
            foreach($where_filtered as $k=>$w){
                $where_format[$this->crudDB->name.'.'.$w] = $where[$w];
                unset($where[$w]);
            }
            $where_format   = array_merge($where,$where_format);
            
            if(!$get = $join_query->get_where(null,$where_format))
                throw new Exception();
            return $get->result();
        }catch(Exception $e){
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_get_join_fail'],$opts));
			return $err;
        }
    }

    /**
     * Destroy item from DB
     *
     * @param string $id
     * @param array $where
     * @param array $opts
     * @return object
     */
	private function delDB($id="",$where=[],$opts=[]){
		try{
            if(!empty($id))
                $where['id'] = $id;
            $select = $this->selectFieldsDB();
            
            if(!empty($where))
                $where  = $this->matchingDataDB($where);
            $query  = $this->db->select(implode(", ",$select));
            $query->where($where);
            if($query->delete($this->crudDB->name) === false )
                throw new Exception();
            $this->resetNameDB();
            return [];
		}
		catch(Exception $e)
		{
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_destroy_fail'],$opts));
			return $err;
		}
	}

    /**
     * update data into db
     *
     * *DONT RUN SELECTFIELD! UPDATE DONT NEED SELECT, USE INSTEAD WHERE!
     * *TO FILTER, JUST RUN SELECTFIELD OUTSIDE UPDATEDB METHOD
     * 
     * @param [type] $id
     * @param [type] $data
     * @return object
     */
	private function updateDB($id,$data,$opts=[]){
		try{
            if(!empty($id))
                $where['id'] = $id;
            $data = $this->matchingDataDB($data);
            
            if(!empty($where))
                $where  = $this->matchingDataDB($where);
            $query      = $this->db->where($where);
            
            if(!$query->update($this->crudDB->name,$data))
                throw new Exception();

            $data = $this->get_one($id,[],['debug'=>true]);
            $this->resetNameDB();
            return arr2Obj($data);
		}
		catch(Exception $e)
		{
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_update_fail'],$opts));
			return $err;
		}
    }

    /**
     * Multi Update DB
     *
     * *DONT RUN SELECTFIELD! UPDATE DONT NEED SELECT, USE INSTEAD WHERE!
     * *TO FILTER, JUST RUN SELECTFIELD OUTSIDE UPDATEDB METHOD
     * 
     * @param array $where
     * @param array $data
     * @param array $opts
     * @return object
     */
    private function batchUpdateDB(array $where,array $data,$opts=[]){
        try{
            $data       = $this->matchingDataDB($data);
            
            if(!empty($where))
                $where  = $this->matchingDataDB($where);            

            $oldData    = $this->find(null,$where,['debug'=>true]);
            $newData    = array_map(function($v) use ($data){
                return array_merge((array)$v,(array)$data);
            },(array) $oldData);
            if(!$this->db->update_batch($this->crudDB->name,$newData,'id'))
                throw new Exception();
            $newData = $this->find(null,$where,['debug'=>true]);
            $this->resetNameDB();
            return arr2Obj($newData);
		}
		catch(Exception $e)
		{
            $err    = $this->errorHandling($e,array_merge(['name'=>'db_multi_update_fail'],$opts));
			return $err;
		}
    }

    // PUBLIC FUNCTION SECTION
    
    function select_fields($fields=[],$opts=[]){
        return $this->selectFieldsDB($fields,$opts);
    }
    
    function create($data,$opts=[]){
        $data['date_added'] = Carbon::now()->timestamp;
        $data['date_created'] = Carbon::now()->timestamp;
        $save = $this->saveDB($data,$opts);
		return arr2Obj($save);
    }
    
    function find_or_create($data,$opts=[]){
        $result   = $this->find_one(null,$data,$opts);
        if(empty($result)){
            if(is_callable([$this,'crudDBCreate']))
                $result     = $this->crudDBCreate($data,$opts);
            else
                $result     = $this->create($data,$opts);
        }
        return arr2Obj($result);
    }

    /**
     * simple get one data from db
     *
     * @param [type] $id
     * @param array $search
     * @param array $opts
     * @return object
     */
	function find_one($id="",$search=[],$opts=[]){
        $find    = $this->getDB($id,$search,$opts);
        if(!empty($find->error))
            return arr2Obj($find);
		return arr2Obj($find[0]);
	}

    function get_one($id,$condition=[],$opts=[]){
        if(empty($id)){
            return null;
        }
        return $this->find_one($id,$condition,$opts);
    }

    /**
     * simple get all data from db
     *
     * @param string $id
     * @param array $search
     * @param array $opts
     * @return object
     */
	function find($id="",$search=[],$opts=[]){
        $find = $this->getDB($id,$search,$opts);
		return arr2Obj($find);
    }
    
    /**
     * easy search then update data, or multiple data
     *
     * @param string $id
     * @param array $data
     * @param array $search
     * @param array $opts
     * @return object
     */
    function search_update($id="",$data=[],$search=[],$opts=[]){
		$data['date_modified'] = Carbon::now()->timestamp;
        if(isset($id))
            $update = $this->updateDB($id,$data,$opts);
        else if(!empty($search) && !empty($data))
            $update = $this->batchUpdateDB($search,$data,$opts);
        else
            $update = arr2Obj(['error'=>'update fail']);
		return arr2Obj($update);    
    }

    function update($id,$data,$opts=[]){
        $data['date_modified'] = Carbon::now()->timestamp;
        $update = $this->updateDB($id,$data,$opts);
        return arr2Obj($update);
    }

    /**
     * destroy data, or multiple data
     *
     * @param string $id
     * @param array $search
     * @param array $opts
     * @return object
     */
    function destroy($id="",$search=[],$opts=[]){
        if(empty($id) && empty($search))
            return arr2Obj(['error'=>'destroy fail']);
        
        $item       = $this->find_one($id,$search);
        if(empty($item))
            return arr2Obj(['error'=>['name'=>'not_found']]);
        
        $destroy    = $this->delDB($id,$search,$opts);
		return arr2Obj($destroy);
    }

    function find_one_join($table,array $foreign_keys,$where=[],$opts=[]){
        $find     = $this->joinGetDB($table,$foreign_keys,$where,$opts);
        if(!empty($find->error))
            return arr2Obj($find);
		return arr2Obj($find[0]);
    }
}


if(!function_exists('strLimit')){
	/**
	 * String limit
	 *
	 * @param string $text
	 * @param int $limit
	 * @return void
	 */
    function strLimit($text, $limit) {
		if (str_word_count($text, 0) > $limit) {
			$words = str_word_count($text, 2);
			$pos = array_keys($words);
			$text = substr($text, 0, $pos[$limit]) . '...';
		}
		return $text;
	}
}