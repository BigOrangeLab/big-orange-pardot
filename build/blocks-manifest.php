<?php
// This file is generated. Do not modify it manually.
return array(
	'big-orange-pardot' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/big-orange-pardot',
		'version' => '0.1.0',
		'title' => 'Big Orange Pardot',
		'category' => 'widgets',
		'icon' => 'feedback',
		'description' => 'A WordPress Form block for WordPress to integrate with Pardot.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'attributes' => array(
			'pardotFormUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'pardotFormHandlerId' => array(
				'type' => 'integer',
				'default' => 0
			)
		),
		'providesContext' => array(
			'big-orange-pardot/formUrl' => 'pardotFormUrl'
		),
		'allowedBlocks' => array(
			'bigorangelab/pardot-field',
			'bigorangelab/pardot-submit'
		),
		'textdomain' => 'big-orange-pardot',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'pardot-field' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/pardot-field',
		'version' => '0.1.0',
		'title' => 'Pardot Field',
		'category' => 'widgets',
		'icon' => 'edit-page',
		'description' => 'A single form field inside a Big Orange Pardot form block.',
		'parent' => array(
			'bigorangelab/big-orange-pardot'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'attributes' => array(
			'fieldName' => array(
				'type' => 'string',
				'default' => ''
			),
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldType' => array(
				'type' => 'string',
				'default' => 'text',
				'enum' => array(
					'text',
					'email',
					'tel',
					'textarea'
				)
			),
			'isRequired' => array(
				'type' => 'boolean',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'default' => ''
			),
			'width' => array(
				'type' => 'string',
				'default' => 'full',
				'enum' => array(
					'full',
					'half'
				)
			)
		),
		'usesContext' => array(
			'big-orange-pardot/formUrl'
		),
		'textdomain' => 'big-orange-pardot',
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'pardot-submit' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/pardot-submit',
		'version' => '0.1.0',
		'title' => 'Pardot Submit Button',
		'category' => 'widgets',
		'icon' => 'button',
		'description' => 'The submit button inside a Big Orange Pardot form block.',
		'parent' => array(
			'bigorangelab/big-orange-pardot'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'attributes' => array(
			'label' => array(
				'type' => 'string',
				'default' => 'Submit'
			)
		),
		'textdomain' => 'big-orange-pardot',
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	)
);
