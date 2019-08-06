<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event observers used in enrol_apply.
 *
 * @package    enrol_apply
 * @copyright  2019 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_forum.
 */
class observer {

    /**
     * Triggered via user_created event.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB, $CFG;

        $eventdata = $event->get_data();

        $userid = $eventdata['objectid'];
        $eventuser = \core_user::get_user($userid);

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'emailchefe']);

        if ($fieldid) {
            $email = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
            if ($email) {
                if (!$DB->record_exists('user', ['email' => $email])) {
                    $namefield = $DB->get_field('user_info_field', 'id', ['shortname' => 'nomechefe']);
                    if ($namefield) {
                        $name = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $namefield]);
                        if ($name) {
                            require_once($CFG->dirroot.'/user/lib.php');
                            $explodedname = explode(' ', $name);
                            $user = new \stdclass();
                            $user->username = $email;
                            $user->email = $email;
                            $user->lastname = array_pop($explodedname);
                            $user->firstname = implode(' ', $explodedname);
                            $password = generate_password();
                            $user->password = $password;

                            $user = signup_setup_new_user($user);
                            $user->confirmed = 1;

                            $newuserid = user_create_user($user);
                            $user->id = $newuserid;

                            $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
                            role_assign($roleid, $newuserid, \context_system::instance()->id);

                            $supportuser = \core_user::get_support_user();

                            $a = new \stdclass();
                            $a->nomechefe = $name;
                            $a->usernamechefe = $user->username;
                            $a->passwordchefe = $password;
                            $a->nomeusuario = fullname($eventuser);

                            $message = get_string('emailgestor', 'enrol_apply', $a);

                            $subject = get_string('emailgestorsubject', 'enrol_apply');

                            // Directly email rather than using the messaging system to ensure its not routed to a popup or jabber.
                            email_to_user($user, $supportuser, $subject, $message);
                        }
                    }
                }
            }
        }
    }
}
