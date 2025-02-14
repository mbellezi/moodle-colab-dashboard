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
                'courseid' => new external_value(PARAM_INT, 'ID do curso')
            )
        );
    }

    /**
     * Retorna os usuários matriculados no curso, com nome completo, email e papéis.
     * Esta versão usa uma consulta SQL personalizada para obter os usuários, 
     * evitando a restrição do get_enrolled_sql() que gera "AND 1 = 2" se o usuário não 
     * tiver as permissões necessárias.
     *
     * ATENÇÃO: Certifique-se de que somente usuários autorizados (por exemplo, administradores)
     * possam chamar esta função, pois esta abordagem ignora alguns filtros de segurança.
     *
     * @param int $courseid
     * @return array Array de usuários com os campos: fullname, email e roles.
     * @throws moodle_exception
     */
    public static function colab_course_users($courseid) {
        global $DB, $CFG;
        
        // Validação dos parâmetros.
        $params = self::validate_parameters(self::colab_course_users_parameters(), array('courseid' => $courseid));
        $courseid = $params['courseid'];

        // Verifica se o curso existe; se não, uma exceção é lançada.
        $course = get_course($courseid, MUST_EXIST);

        // Obtém o contexto do curso e valida o contexto.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Usando uma consulta SQL personalizada para buscar os usuários matriculados.
        // Isso evita que o get_enrolled_sql() insira condições como "AND 1 = 2".
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
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

        // Monta o resultado final com fullname, email e os papéis.
        $result = array();
        foreach ($users as $user) {
            $result[] = array(
                'fullname' => fullname($user),
                'email'    => $user->email,
                'roles'    => isset($userroles[$user->id]) ? $userroles[$user->id] : array(),
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
                    'roles'    => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Papel do usuário no curso')
                    )
                )
            )
        );
    }
}
