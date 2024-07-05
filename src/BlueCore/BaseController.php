<?php
namespace BlueFission\BlueCore;

use BlueFission\Services\Service;
use BlueFission\Services\Model\BaseModel;

/**
 * Class BaseController
 *
 * This class extends the Service class and is used as a base class for controllers.
 * It contains a `response` method to handle the response of the data received from a model.
 */
class BaseController extends Service {

	// /**
	//  * Creates a response from the data received from a model.
	//  *
	//  * @param mixed $data The data received from a model.
	//  *
	//  * @return \Illuminate\Http\Response
	//  */
	// protected function response( $data )
	// {
	// 	$response = [];
	// 	if ( $data instanceof BaseModel ) {
	// 		$response = [
	// 			'id' => $data->id(),
	// 			'children' => $data->children(),
	// 			'list' => $data->contents(),
	// 			'data' => $data->data(),
	// 			'status'=> $data->status(),
	// 		];
	// 		/*
	// 		if ( env('DEBUG') && method_exists ( $this->_dataObject, 'query' )) {
	// 			$response['info'] = $this->_dataObject->query();
	// 		}
	// 		*/
	// 	} else {
	// 		return response($data);
	// 	}
	// 	return response($response);
	// }
}
