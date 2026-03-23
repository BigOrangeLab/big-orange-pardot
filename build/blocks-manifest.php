<?php
// This file is generated. Do not modify it manually.
return array(
	'pardot-field' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/pardot-field',
		'version' => '1.0.1',
		'title' => 'Pardot Field',
		'category' => 'widgets',
		'icon' => 'edit-page',
		'description' => 'A single form field inside a Big Orange Pardot form block.',
		'parent' => array(
			'bigorangelab/pardot-form'
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
	'pardot-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'bigorangelab/pardot-form',
		'version' => '1.0.1',
		'title' => 'Pardot Form',
		'category' => 'widgets',
		'icon' => 'feedback',
		'description' => 'A WordPress Form block for WordPress to integrate with Pardot.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false,
			'color' => array(
				'background' => true,
				'text' => false
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			),
			'border' => array(
				'color' => true,
				'radius' => true,
				'style' => true,
				'width' => true
			)
		),
		'attributes' => array(
			'pardotFormUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'pardotFormHandlerId' => array(
				'type' => 'integer',
				'default' => 0
			),
			'fieldLabelColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldInputBg' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldBorderColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldFocusColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldBorderRadius' => array(
				'type' => 'string',
				'default' => ''
			),
			'submitLabel' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonTextColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBgColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBgGradient' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonHoverBgColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBorderColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBorderWidth' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBorderStyle' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonBorderRadius' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonPadding' => array(
				'type' => 'object',
				'default' => array(
					
				)
			),
			'buttonShadow' => array(
				'type' => 'string',
				'default' => ''
			),
			'buttonAlignment' => array(
				'type' => 'string',
				'default' => 'left'
			)
		),
		'providesContext' => array(
			'big-orange-pardot/formUrl' => 'pardotFormUrl'
		),
		'allowedBlocks' => array(
			'bigorangelab/pardot-field'
		),
		'textdomain' => 'big-orange-pardot',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
