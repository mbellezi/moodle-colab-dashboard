<?php
// Define the web service function.
$functions = array(
    'local_colab_course_users' => array(
         'classname'   => 'local_colab_external',
         'methodname'  => 'colab_course_users',
         'classpath'   => 'local/colab/externallib.php',
         'description' => 'Retorna os usuários matriculados no curso com nome completo, email e papéis.',
         'type'        => 'read',
         'capabilities'=> 'moodle/course:view'
    )
);

// Register the service that exposes the function.
$services = array(
    'Colab Service' => array(
         'functions' => array('local_colab_course_users'),
         'restrictedusers' => 0,
         'enabled' => 1,
    )
);
