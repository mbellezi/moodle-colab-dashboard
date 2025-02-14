<?php
// Define as funções do web service.
$functions = array(
    'local_colab_course_users' => array(
         'classname'   => 'local_colab_external',
         'methodname'  => 'colab_course_users',
         'classpath'   => 'local/colab/externallib.php',
         'description' => 'Retorna os usuários matriculados no curso com nome completo, email e papéis.',
         'type'        => 'read',
         'capabilities'=> 'moodle/course:view'
    ),
    'local_colab_course_users_count' => array(
         'classname'   => 'local_colab_external',
         'methodname'  => 'colab_course_users_count',
         'classpath'   => 'local/colab/externallib.php',
         'description' => 'Retorna o número de usuários matriculados no curso, opcionalmente filtrado por papel.',
         'type'        => 'read',
         'capabilities'=> 'moodle/course:view'
    )
);

// Registra o serviço que expõe as funções.
$services = array(
    'Colab Service' => array(
         'functions' => array('local_colab_course_users', 'local_colab_course_users_count'),
         'restrictedusers' => 0,
         'enabled' => 1,
    )
);
