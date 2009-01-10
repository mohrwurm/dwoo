<?php

/**
 * Defines a sub-template that can then be called (even recursively) with the defined arguments
 * <pre>
 *  * name : template name
 *  * rest : list of arguments and optional default values
 * </pre>
 * This software is provided 'as-is', without any express or implied warranty.
 * In no event will the authors be held liable for any damages arising from the use of this software.
 *
 * @author     Jordi Boggiano <j.boggiano@seld.be>
 * @copyright  Copyright (c) 2008, Jordi Boggiano
 * @license    http://dwoo.org/LICENSE   Modified BSD License
 * @link       http://dwoo.org/
 * @version    1.0.1
 * @date       2008-12-24
 * @package    Dwoo
 */
class Dwoo_Plugin_template extends Dwoo_Block_Plugin implements Dwoo_ICompilable_Block
{
	public function init($name, array $rest = array())
	{
	}

	public static function preProcessing(Dwoo_Compiler $compiler, array $params, $prepend, $append, $type)
	{
		$params = $compiler->getCompiledParams($params);
		$parsedParams = array();
		foreach ($params['*'] as $param=>$defValue) {
			if (is_numeric($param)) {
				$param = $defValue;
				$defValue = null;
			}
			$param = trim($param, '\'"');
			if (!preg_match('#^[a-z0-9_]+$#i', $param)) {
				throw new Dwoo_Compilation_Exception($compiler, 'Function : parameter names must contain only A-Z, 0-9 or _');
			}
			$parsedParams[$param] = $defValue;
		}
		$params['name'] = substr($params['name'], 1, -1);
		$params['*'] = $parsedParams;
		$params['uuid'] = uniqid();
		$compiler->addTemplatePlugin($params['name'], $parsedParams, $params['uuid']);
		$currentBlock =& $compiler->getCurrentBlock();
		$currentBlock['params'] = $params;
		return '';
	}

	public static function postProcessing(Dwoo_Compiler $compiler, array $params, $prepend, $append, $content)
	{
		$paramstr = 'Dwoo $dwoo';
		$init = 'static $_callCnt = 0;'."\n".
		'$dwoo->scope[\' '.$params['uuid'].'\'.$_callCnt] = array();'."\n".
		'$_scope = $dwoo->setScope(array(\' '.$params['uuid'].'\'.($_callCnt++)));'."\n/* -- template start output */";
		$cleanup = '/* -- template end output */ $dwoo->setScope($_scope, true);';
		foreach ($params['*'] as $param=>$defValue) {
			if ($defValue === null) {
				$paramstr.=', $'.$param;
			} else {
				$paramstr.=', $'.$param.' = '.$defValue;
			}
			$init .= '$dwoo->scope[\''.$param.'\'] = $'.$param.";\n";
		}
		$body = Dwoo_Compiler::PHP_OPEN.'function Dwoo_Plugin_'.$params['name'].'_'.$params['uuid'].'('.$paramstr.') {'."\n$init".Dwoo_Compiler::PHP_CLOSE.
			$prepend.str_replace(array('$this->','$this,'), array('$dwoo->', '$dwoo,'), $content).$append.
			Dwoo_Compiler::PHP_OPEN.$cleanup."\n}".Dwoo_Compiler::PHP_CLOSE;
		$compiler->addTemplatePlugin($params['name'], $params['*'], $params['uuid'], $body);
	}
}
