<?php
// Este arquivo é parte do plugin local_colab.

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class local_colab_external extends external_api {

    /**
     * Define os parâmetros de entrada para a função colab_course_users.
     *
     * @return external_function_parameters
     */
    public static function colab_course_users_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'ID do curso'),
                'role'     => new external_value(PARAM_TEXT, 'Papél do usuário (opcional)', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Retorna os usuários matriculados no curso, com nome completo, email, CPF, username e o primeiro papel encontrado.
     * Se o parâmetro "role" for informado, apenas os usuários que possuírem esse papel serão retornados.
     *
     * ATENÇÃO: Certifique-se de que somente usuários autorizados (ex: administradores)
     * possam chamar esta função, pois esta abordagem ignora alguns filtros de segurança.
     *
     * @param int $courseid
     * @param string $role (opcional) Identificador do papel a filtrar.
     * @return array Array de usuários com os campos: fullname, email, cpf, username e role.
     * @throws moodle_exception
     */
    public static function colab_course_users($courseid, $role = '') {
        global $DB, $CFG;
        
        // Validação dos parâmetros.
        $params = self::validate_parameters(
            self::colab_course_users_parameters(),
            array('courseid' => $courseid, 'role' => $role)
        );
        $courseid   = $params['courseid'];
        $rolefilter = $params['role'];

        // Verifica se o curso existe; se não, lança exceção.
        $course = get_course($courseid, MUST_EXIST);

        // Obtém e valida o contexto do curso.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Consulta SQL para obter os usuários matriculados, incluindo o campo username.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.username
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE e.courseid = :courseid AND u.deleted = 0";
        $users = $DB->get_records_sql($sql, array('courseid' => $courseid));

        if (empty($users)) {
            return array();
        }

        // Obter os papéis de todos os usuários com uma única consulta.
        list($useridsql, $userparams) = $DB->get_in_or_equal(array_keys($users));
        $sqlroles = "SELECT ra.userid, r.shortname
                     FROM {role_assignments} ra
                     JOIN {role} r ON ra.roleid = r.id
                     WHERE ra.contextid = ? AND ra.userid $useridsql";
        $queryparams = array_merge(array($context->id), $userparams);
        $roleassignments = $DB->get_records_sql($sqlroles, $queryparams);

        // Agrupar os papéis por usuário.
        $userroles = array();
        foreach ($roleassignments as $ra) {
            if (!isset($userroles[$ra->userid])) {
                $userroles[$ra->userid] = array();
            }
            $userroles[$ra->userid][] = $ra->shortname;
        }

        // Buscar o valor do campo extra "CPF" para os usuários.
        $usercpf = array();
        if ($cpffield = $DB->get_record('user_info_field', array('shortname' => 'CPF'))) {
            list($useridsql, $userparams) = $DB->get_in_or_equal(array_keys($users));
            $sqlcpf = "SELECT userid, data FROM {user_info_data} WHERE fieldid = ? AND userid $useridsql";
            $queryparams = array_merge(array($cpffield->id), $userparams);
            $cpfdata = $DB->get_records_sql($sqlcpf, $queryparams);
            foreach ($cpfdata as $data) {
                $usercpf[$data->userid] = preg_replace('/\D/', '', $data->data);
            }
        }

        // Monta o resultado final.
        $result = array();
        foreach ($users as $user) {
            // Verifica os papéis do usuário.
            if (isset($userroles[$user->id]) && !empty($userroles[$user->id])) {
                // Se houver filtro de papel, o usuário só será considerado se possuir o papel informado.
                if ($rolefilter !== '' && !in_array($rolefilter, $userroles[$user->id])) {
                    continue;
                }
                // Define o papel a retornar.
                $userrole = ($rolefilter !== '') ? $rolefilter : reset($userroles[$user->id]);
            } else {
                // Se não houver papel e houver filtro, desconsidera o usuário.
                if ($rolefilter !== '') {
                    continue;
                }
                $userrole = '';
            }
            $result[] = array(
                'fullname' => fullname($user),
                'email'    => $user->email,
                'cpf'      => isset($usercpf[$user->id]) ? $usercpf[$user->id] : '',
                'username' => $user->username, // Novo campo adicionado
                'role'     => $userrole,
            );
        }

        return $result;
    }

    /**
     * Define a estrutura dos dados de retorno da função colab_course_users.
     *
     * @return external_multiple_structure
     */
    public static function colab_course_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'fullname' => new external_value(PARAM_TEXT, 'Nome completo do usuário'),
                    'email'    => new external_value(PARAM_EMAIL, 'Email do usuário'),
                    'cpf'      => new external_value(PARAM_TEXT, 'CPF do usuário'),
                    'username' => new external_value(PARAM_TEXT, 'Nome de usuário do usuário'), // Novo campo adicionado
                    'role'     => new external_value(PARAM_TEXT, 'Papel do usuário no curso'),
                )
            )
        );
    }

    /**
     * Define os parâmetros de entrada para a função colab_course_users_count.
     *
     * @return external_function_parameters
     */
    public static function colab_course_users_count_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'ID do curso'),
                'role'     => new external_value(PARAM_TEXT, 'Papél do usuário (opcional)', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Retorna o número de usuários matriculados no curso, opcionalmente filtrado por papel.
     *
     * @param int $courseid
     * @param string $role (opcional) Identificador do papel a filtrar.
     * @return int Número de usuários.
     * @throws moodle_exception
     */
    public static function colab_course_users_count($courseid, $role = '') {
        global $DB;
        
        // Validação dos parâmetros.
        $params = self::validate_parameters(
            self::colab_course_users_count_parameters(),
            array('courseid' => $courseid, 'role' => $role)
        );
        $courseid   = $params['courseid'];
        $rolefilter = $params['role'];

        // Verifica se o curso existe; se não, lança exceção.
        $course = get_course($courseid, MUST_EXIST);

        // Obtém e valida o contexto do curso.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        if ($rolefilter !== '') {
            // Conta usuários matriculados que possuam o papel especificado.
            $sql = "SELECT COUNT(DISTINCT u.id)
                    FROM {user} u
                    JOIN {user_enrolments} ue ON ue.userid = u.id
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {role} r ON r.id = ra.roleid
                    WHERE e.courseid = :courseid
                      AND u.deleted = 0
                      AND ra.contextid = :contextid
                      AND r.shortname = :role";
            $queryparams = array(
                'courseid'  => $courseid,
                'contextid' => $context->id,
                'role'      => $rolefilter
            );
            $count = $DB->count_records_sql($sql, $queryparams);
        } else {
            // Conta todos os usuários matriculados no curso.
            $sql = "SELECT COUNT(DISTINCT u.id)
                    FROM {user} u
                    JOIN {user_enrolments} ue ON ue.userid = u.id
                    JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE e.courseid = :courseid
                      AND u.deleted = 0";
            $queryparams = array('courseid' => $courseid);
            $count = $DB->count_records_sql($sql, $queryparams);
        }

        return $count;
    }

    /**
     * Define a estrutura dos dados de retorno da função colab_course_users_count.
     *
     * @return external_value
     */
    public static function colab_course_users_count_returns() {
        return new external_value(PARAM_INT, 'Número de usuários inscritos no curso');
    }
}
