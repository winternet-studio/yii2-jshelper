<?php
namespace winternet\yii2;

class Common {
	public static function processAjaxSubmit($options = []) {
		/*
		DESCRIPTION:
		- generate Javascript code for handling response of an Ajax request that produces standard result/output JSON object with 'status', 'result_msg', and 'err_msg' keys in an array
		INPUT:
		- $options : associative array with any of these keys:
			- 'form' : ActiveForm object
			- 'on_error' : name of callback function when submission caused some errors
			- 'on_success' : name of callback function when submission succeeded
			- 'on_complete' : name of callback function that will always be called
		OUTPUT:
		- Javascript expression
		*/
		$js = "function(rsp) {";
		if ($options['form']) {
			// Apply the server-side generated errors to the form fields
			$js .= "var form = $(_clickedButton).parents('form');
var errorCount = 0, a = [];
if (typeof rsp.err_msg_ext != 'undefined') {
	for (var x in rsp.err_msg_ext) {if (rsp.err_msg_ext.hasOwnProperty(x)){errorCount++;}}
	a = rsp.err_msg_ext;
}form.yiiActiveForm('updateMessages', a);";  // NOTE: errorCount MUST be determined before form.yiiActiveForm() because it modifies rsp.err_msg_ext! NOTE: updateMessages should always be called so that in case there are no error any previously set errors are cleared.
		} else {
			$js .= "var form, errorCount;";
			$js .= "if (rsp.err_msg) errorCount = rsp.err_msg.length;";
		}

		if ($options['on_error'] || $options['on_success']) {
			$js .= "if (errorCount > 0) {". ($options['on_error'] ? $options['on_error'] .'({form:form, rsp:rsp, errorCount:errorCount});' : '') ."} else {". ($options['on_success'] ? $options['on_success'] .'({form:form, rsp:rsp, errorCount:errorCount});' : '') ."}";
		}
		if ($options['on_complete']) {
			$js .= $options['on_complete'] .'({form:form, rsp:rsp, errorCount:errorCount});';
		}
		$js .= "}";
		return new \yii\web\JsExpression($js);
	}

	public static function processAjaxSubmitError($options = []) {
		/*
		DESCRIPTION:
		- generate Javascript code for handling a failed Ajax request with a JSON response, eg. a 500 Internal Server Error
		INPUT:
		- $options : associative array with any of these keys:
			- 'minimal' : set to true to generate very minimal code
		OUTPUT:
		- Javascript expression
		*/
		if ($options['minimal']) {
			$js = "function(r,t,e) {";
			$js .= "alert(e+\"\\n\\n\"+\$('<div/>').html(r.responseJSON.message).text());";
			$js .= "}";
		} else {
			$js = "function(xhr, textStatus, errorThrown) {";
			$js .= "var \$bg = \$('<div/>').addClass('jfw-yii2-ajax-error-bg').css({position: 'fixed', top: '0px', left: '0px', width: '100%', backgroundColor: '#595959'}).height(\$(window).height());";
			$js .= "var \$modal = \$('<div/>').addClass('msg').css({position: 'fixed', top: '100px', left: '50%', transform: 'translateX(-50%)', width: '70%', marginLeft: 'auto', marginRight: 'auto', backgroundColor: '#EEEEEE', padding: '30px', boxShadow: '0px 0px 28px 5px #232323'});";
			$js .= "\$modal.html('<h3>'+ errorThrown +'</h3>'+ xhr.responseJSON.message +'<div><button class=\"btn btn-primary\" onclick=\"\$(this).parent().parent().parent().remove();\">OK</button></div>');";
			$js .= "\$bg.append(\$modal);";
			$js .= "\$('body').append(\$bg);";
			$js .= "}";
		}
		return new \yii\web\JsExpression($js);
	}

	public static function addResultErrors($result, &$model, $options = []) {
		/*
		DESCRIPTION:
		- add errors from a Yii model to a standard result array
		- the new key 'err_msg_ext' MUST then be used for processing it (because 'err_msg' might not contain all error messages)
		INPUT:
		- $result : empty variable (null, false, whatever) or an associative array in this format: ['status' => 'ok|error', 'result_msg' => [], 'err_msg' => []]
		- $model : a Yii model
		- $options : associative array with any of these keys: 
			- 'add_existing' : add the existing 'err_msg' array entries to 'err_msg_ext'
		OUTPUT:
		- associative array in the format of $result but with the new key 'err_msg_ext'
		*/
		if (!is_array($result)) {
			$result = [
				'status' => 'ok',
				'result_msg' => [],
				'err_msg' => [],
			];
		}

		$modelErrors = $model->getErrors();

		foreach ($modelErrors as $attr => $errors) {
			// Generate the form field ID so Yii ActiveForm client-side can apply the error message
			if (!$modelName) {
				$modelName = $model::className();
				$modelName = mb_strtolower(substr($modelName, strrpos($modelName, '\\')+1));
			}

			$result['err_msg_ext'][$modelName .'-'. mb_strtolower($attr) ] = $errors;
		}


		if ($options['add_existing']) {
			if (!empty($result['err_msg'])) {
				$result['err_msg_ext']['_global'] = $result['err_msg'];
			}
		}

		// Ensure correct status
		if (!empty($result['err_msg']) || !empty($result['err_msg_ext'])) {
			$result['status'] = 'error';
		}

		return $result;
	}

	public static function filterAttributes($all_attributes, $attributes_to_keep) {
		/*
		DESCRIPTION:
		- takes an array of attributes in the form they are provided to eg. GridView or DetailView, and return new array where only the desired ones have been kept
		INPUT:
		- 
		OUTPUT:
		- 
		*/
		if (!is_array($all_attributes)) {
			new \app\components\Error('Invalid list of attributes to filtering.', ['Attributes' => $all_attributes]);
		}
		if (!is_array($attributes_to_keep)) {
			new \app\components\Error('Invalid list of attributes to keep when filtering attributes.', ['Attributes' => $attributes_to_keep]);
		}

		$output = [];
		foreach ($all_attributes as $value) {
			if (is_string($value)) {
				$colon = strpos($value, ':');
				if ($colon === false) {
					$name = $value;
				} else {
					$name = substr($value, 0, $colon);
				}
			} elseif (is_array($value)) {
				if ($value['attribute']) {
					$name = $value['attribute'];
				} elseif ($value['class']) {
					// always leave special entries intact (eg. class=yii\grid\ActionColumn)
					$output[] = $value;
					continue;
				} else {
					new \app\components\Error('Attributes name not found when filtering attributes.', ['Attributes' => $all_attributes]);
				}
			} else {
				new \app\components\Error('Invalid value for filtering attributes.', ['Attributes' => $all_attributes]);
			}

			if (in_array($name, $attributes_to_keep)) {
				$output[] = $value;
			}
		}

		return $output;
	}
}
