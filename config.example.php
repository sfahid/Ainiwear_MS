<?php
return [
    'db' => ['host'=>'127.0.0.1','port'=>3306,'name'=>'aini_wear','user'=>'aini_app','password'=>'CHANGE_ME'],
    'timezone' => 'Asia/Karachi',
    'ai' => ['provider'=>'chatgpt','key'=>getenv('OPENAI_API_KEY') ?: '', 'model'=>getenv('OPENAI_MODEL') ?: 'gpt-6.1-sol', 'endpoint'=>'https://api.openai.com/v1/responses'],
];
