<?php
/* For licensing terms, see /license.txt */

/**
 * This tool allows platform admins to update users by uploading a CSV file.
 *
 * @package chamilo.admin
 */

use Ddeboer\DataImport\Reader\CsvReader;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Validate the imported data.
 */
$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

// Set this option to true to enforce strict purification for usenames.
$purification_option_for_usernames = false;

/**
 * @param array $users
 *
 * @return array
 */
function validate_data($users)
{
    global $defined_auth_sources;
    $errors = [];
    $usernames = [];

    $classExistList = [];
    $usergroup = new UserGroup();

    foreach ($users as $user) {
        // 2. Check username, first, check whether it is empty.
        if (isset($user['NewUserName'])) {
            if (!UserManager::is_username_empty($user['NewUserName'])) {
                // 2.1. Check whether username is too long.
                if (UserManager::is_username_too_long($user['NewUserName'])) {
                    $errors[$user['UserName']][] = get_lang('UserNameTooLong');
                }
                // 2.2. Check whether the username was used twice in import file.
                if (isset($usernames[$user['NewUserName']])) {
                    $errors[$user['UserName']][] = get_lang('UserNameUsedTwice');
                }
                $usernames[$user['UserName']] = 1;
                // 2.3. Check whether username is allready occupied.
                if (!UserManager::is_username_available($user['NewUserName']) &&
                    $user['NewUserName'] != $user['UserName']
                ) {
                    $errors[$user['UserName']][] = get_lang('UserNameNotAvailable');
                }
            }
        }

        // 3. Check status.
        if (isset($user['Status']) && !api_status_exists($user['Status'])) {
            $errors[$user['UserName']][] = get_lang('WrongStatus');
        }

        // 4. Check ClassId
        if (!empty($user['ClassId'])) {
            $classId = explode('|', trim($user['ClassId']));
            foreach ($classId as $id) {
                if (in_array($id, $classExistList)) {
                    continue;
                }
                $info = $usergroup->get($id);
                if (empty($info)) {
                    $errors[$user['UserName']][] = sprintf(get_lang('ClassIdDoesntExists'), $id);
                } else {
                    $classExistList[] = $info['id'];
                }
            }
        }

        // 5. Check authentication source
        if (!empty($user['AuthSource'])) {
            if (!in_array($user['AuthSource'], $defined_auth_sources)) {
                $errors[$user['UserName']][] = get_lang('AuthSourceNotAvailable');
            }
        }
    }

    return $errors;
}

/**
 * Update users from the imported data.
 *
 * @param array $users         List of users.
 * @param bool  $resetPassword Optional.
 */
function updateUsers($users, $resetPassword = false)
{
    $usergroup = new UserGroup();

    if (is_array($users)) {
        foreach ($users as $user) {
            if (isset($user['Status'])) {
                $user['Status'] = api_status_key($user['Status']);
            }

            $userInfo = api_get_user_info_from_username($user['UserName']);

            if (empty($userInfo)) {
                continue;
            }

            $user_id = $userInfo['user_id'];

            $firstName = isset($user['FirstName']) ? $user['FirstName'] : $userInfo['firstname'];
            $lastName = isset($user['LastName']) ? $user['LastName'] : $userInfo['lastname'];
            $userName = isset($user['NewUserName']) ? $user['NewUserName'] : $userInfo['username'];
            $changePassMethod = 0;
            $password = null;
            $authSource = $userInfo['auth_source'];

            if ($resetPassword) {
                $changePassMethod = 1;
            } else {
                if (isset($user['Password'])) {
                    $changePassMethod = 2;
                    $password = $user['Password'];
                }

                if (isset($user['AuthSource']) && $user['AuthSource'] != $authSource) {
                    $authSource = $user['AuthSource'];
                    $changePassMethod = 3;
                }
            }

            $email = isset($user['Email']) ? $user['Email'] : $userInfo['email'];
            $status = isset($user['Status']) ? $user['Status'] : $userInfo['status'];
            $officialCode = isset($user['OfficialCode']) ? $user['OfficialCode'] : $userInfo['official_code'];
            $phone = isset($user['PhoneNumber']) ? $user['PhoneNumber'] : $userInfo['phone'];
            $pictureUrl = isset($user['PictureUri']) ? $user['PictureUri'] : $userInfo['picture_uri'];
            $expirationDate = isset($user['ExpiryDate']) ? $user['ExpiryDate'] : $userInfo['expiration_date'];
            $active = isset($user['Active']) ? $user['Active'] : $userInfo['active'];
            $creatorId = $userInfo['creator_id'];
            $hrDeptId = $userInfo['hr_dept_id'];
            $language = isset($user['Language']) ? $user['Language'] : $userInfo['language'];
            $sendEmail = isset($user['SendEmail']) ? $user['SendEmail'] : $userInfo['language'];
            $userUpdated = UserManager::update_user(
                $user_id,
                $firstName,
                $lastName,
                $userName,
                $password,
                $authSource,
                $email,
                $status,
                $officialCode,
                $phone,
                $pictureUrl,
                $expirationDate,
                $active,
                $creatorId,
                $hrDeptId,
                null,
                $language,
                '',
                false,
                $changePassMethod
            );
            if (!empty($user['Courses']) && !is_array($user['Courses'])) {
                $user['Courses'] = [$user['Courses']];
            }
            if (!empty($user['Courses']) && is_array($user['Courses'])) {
                foreach ($user['Courses'] as $course) {
                    if (CourseManager::course_exists($course)) {
                        CourseManager::subscribeUser($user_id, $course, $user['Status']);
                    }
                }
            }
            if (!empty($user['ClassId'])) {
                $classId = explode('|', trim($user['ClassId']));
                foreach ($classId as $id) {
                    $usergroup->subscribe_users_to_usergroup(
                        $id,
                        [$user_id],
                        false
                    );
                }
            }

            // Saving extra fields.
            global $extra_fields;

            // We are sure that the extra field exists.
            foreach ($extra_fields as $extras) {
                if (isset($user[$extras[1]])) {
                    $key = $extras[1];
                    $value = $user[$extras[1]];
                    UserManager::update_extra_field_value(
                        $user_id,
                        $key,
                        $value
                    );
                }
            }
        }
    }
}

/**
 * Anonymizes users listed in the imported data.
 * Error are logged into $errors for
 *  - usernames that are not found in the database;
 *  - usernames for which anonymization fails.
 *
 * @param array $usernames the user names of the users to be anonymized
 * @param array $errors    error list recipient, indexed by username (as returned by validate_data)
 */
function anonymizeUsers($usernames, &$errors)
{
    /** @var \Chamilo\UserBundle\Entity\User[] $users */
    $users = UserManager::getRepository()->matching(
        Criteria::create()->where(
            Criteria::expr()->in('username', $usernames)
        )
    );
    $foundUsernames = [];
    foreach ($users as $user) {
        $foundUsernames[] = $user->getUsername();
        try {
            if (!UserManager::anonymize($user->getId())) {
                $errors[$user->getUsername()][] = get_lang('ErrorsFound');
            }
        } catch (Exception $exception) {
            $errors[$user->getUsername()][] = $exception->getMessage();
        }
    }
    foreach (array_diff($usernames, $foundUsernames) as $usernameNotFound) {
        $errors[$usernameNotFound][] = get_lang('NotFound');
    }
}

/**
 * Read the CSV-file.
 *
 * @param string $file                 Path to the CSV-file
 * @param bool   $enforceOneColumnRule Make sure there is only one column
 *
 * @return array All user information read from the file
 * @throws Exception on missing mandatory column 'UserName' or wrong number of columns
 */
function parse_csv_data($file, $enforceOneColumnRule = false)
{
    $file = new SplFileObject($file);
    $csv = new CsvReader($file, ';');
    $csv->setHeaderRowNumber(0);

    $columnHeaders = $csv->getColumnHeaders();
    if (!in_array('UserName', $columnHeaders)) {
        throw new Exception(get_lang("UserNameMandatory"));
    }

    if ($enforceOneColumnRule && count($columnHeaders) > 1) {
        throw new Exception(get_lang('CSVAnonymizeIncorrectFormatOnlyProvideUsername'));
    }

    $users = [];

    foreach ($csv as $row) {
        if (isset($row['Courses'])) {
            $row['Courses'] = explode('|', trim($row['Courses']));
        }

        $users[] = $row;
    }

    return $users;
}

$this_section = SECTION_PLATFORM_ADMIN;
api_protect_admin_script(true, null);

$defined_auth_sources[] = PLATFORM_AUTH_SOURCE;

if (isset($extAuthSource) && is_array($extAuthSource)) {
    $defined_auth_sources = array_merge($defined_auth_sources, array_keys($extAuthSource));
}

$tool_name = get_lang('EditUserListCSV');
$interbreadcrumb[] = ["url" => 'index.php', "name" => get_lang('PlatformAdmin')];

set_time_limit(0);
$extra_fields = UserManager::get_extra_fields(0, 0, 5, 'ASC', true);

$form = new FormValidator('user_update_import', 'post', api_get_self());
$form->addElement('header', $tool_name);
$form->addFile('import_file', get_lang('ImportFileLocation'), ['accept' => 'text/csv', 'id' => 'import_file']);
$form->addCheckBox('reset_password', '', get_lang('AutoGeneratePassword'));
$form->addGroup(
    [
        $form->createElement('radio', 'action', null, get_lang('Edit'), 'edit'),
        $anonymizeRadio = $form->createElement('radio', 'action', null, get_lang('Anonymize'), 'anonymize'),
    ],
    'actions',
    get_lang('Action')
);
$form->setAttribute(
    'onsubmit',
    'javascript:'
    .'!document.forms[0].elements["'.$anonymizeRadio->getAttribute('id').'"].checked'
    .'||'.
    'confirm("'.get_lang('AreYouSureToAnonymize').'")'
);

if ($form->validate() && Security::check_token('post')) {
    Security::clear_token();

    $formValues = $form->exportValues();
    $action = $formValues['actions']['action'];

    if (empty($_FILES['import_file']) || empty($_FILES['import_file']['size'])) {
        header('Location: '.api_get_self());
        exit;
    }

    $uploadInfo = pathinfo($_FILES['import_file']['name']);

    if ($uploadInfo['extension'] !== 'csv') {
        Display::addFlash(
            Display::return_message(get_lang('NotCSV'), 'error')
        );

        header('Location: '.api_get_self());
        exit;
    }

    try {
        $users = parse_csv_data($_FILES['import_file']['tmp_name'], 'anonymize' === $action);
    } catch (Exception $exception) {
        Display::addFlash(
            Display::return_message($exception->getMessage(), 'error')
        );

        header('Location: '.api_get_self());
        exit;
    }

    $errors = validate_data($users);
    $errorUsers = array_keys($errors);
    $usersToUpdate = [];

    foreach ($users as $user) {
        if (!in_array($user['UserName'], $errorUsers)) {
            $usersToUpdate[] = $user;
        }
    }

    if ('anonymize' === $action) {
        anonymizeUsers(array_column($usersToUpdate, 'UserName'), $errors);
    } else { // 'edit'
        updateUsers($usersToUpdate, isset($formValues['reset_password']));
    }

    if (empty($errors)) {
        Display::addFlash(
            Display::return_message(get_lang('FileImported'), 'success')
        );
    } else {
        $warningMessage = '';

        foreach ($errors as $errorUsername => $errorUserMessages) {
            $warningMessage .= "<strong>$errorUsername</strong>";
            $warningMessage .= '<ul><li>'.implode('</li><li>', $errorUserMessages).'</li></ul>';
        }

        Display::addFlash(
            Display::return_message(get_lang('ErrorsFound'), 'warning')
        );
        Display::addFlash(
            Display::return_message($warningMessage, 'warning', false)
        );
    }

    header('Location: '.api_get_self());
    exit;
}

Display::display_header($tool_name);

$token = Security::get_token();

$form->addHidden('sec_token', $token);
$form->addButtonImport(get_lang('Import'));
$form->display();

$list = [];
$list_reponse = [];
$result_xml = '';
$i = 0;
$count_fields = count($extra_fields);
if ($count_fields > 0) {
    foreach ($extra_fields as $extra) {
        $list[] = $extra[1];
        $list_reponse[] = 'xxx';
        $spaces = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $result_xml .= $spaces.'&lt;'.$extra[1].'&gt;xxx&lt;/'.$extra[1].'&gt;';
        if ($i != $count_fields - 1) {
            $result_xml .= '<br/>';
        }
        $i++;
    }
}

?>
    <p><?php echo get_lang('CSVMustLookLike').' ('.get_lang('MandatoryFields').')'; ?> :</p>
    <blockquote>
    <pre>
        <b>UserName</b>;LastName;FirstName;Email;NewUserName;Password;AuthSource;OfficialCode;PhoneNumber;Status;ExpiryDate;Active;Language;Courses;ClassId;
        xxx;xxx;xxx;xxx;xxx;xxx;xxx;xxx;xxx;user/teacher/drh;YYYY-MM-DD 00:00:00;0/1;xxx;<span
            style="color:red;"><?php if (count($list_reponse) > 0) {
    echo implode(';', $list_reponse).';';
} ?></span>xxx1|xxx2|xxx3;1;<br/>
    </pre>
    </blockquote>
    <p>
<?php
Display::display_footer();
