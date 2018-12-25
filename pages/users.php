<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'users', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// PREPARE LIST OF OPTIONS
$optionsManagedBy = '';
$optionsRoles = '';
$userRoles = explode(';', $_SESSION['fonction_id']);
// If administrator then all roles are shown
// else only the Roles the users is associated to.
if ((int) $_SESSION['is_admin'] === 1) {
    $optionsManagedBy .= '<option value="0">'.langHdl('administrators_only').'</option>';
}

$rows = DB::query(
    'SELECT id, title, creator_id
    FROM '.prefixTable('roles_title').'
    ORDER BY title ASC'
);
foreach ($rows as $record) {
    if ((int) $_SESSION['is_admin'] === 1 || in_array($record['id'], $_SESSION['user_roles']) === true) {
        $optionsManagedBy .= '<option value="'.$record['id'].'">'.langHdl('managers_of').' '.addslashes($record['title']).'</option>';
    }
    if ((int) $_SESSION['is_admin'] === 1
        || ((int) $_SESSION['user_manager'] === 1
        && (in_array($record['id'], $userRoles) === true || (int) $record['creator_id'] === (int) $_SESSION['user_id']))
    ) {
        $optionsRoles .= '<option value="'.$record['id'].'">'.addslashes($record['title']).'</option>';
    }
}

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();
$foldersList = '';
foreach ($treeDesc as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
    ) {
        $ident = '';
        for ($y = 1; $y < $t->nlevel; ++$y) {
            $ident .= '&nbsp;&nbsp;';
        }
        $foldersList .= '<option value="'.$t->id.'">'.$ident.htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8').'</option>';
        $prev_level = $t->nlevel;
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
        <h1 class="m-0 text-dark">
        <i class="fas fa-users mr-2"></i><?php echo langHdl('users'); ?>
        </h1>
        </div><!-- /.col -->
    </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="row" id="row-list">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-middle">
                    <h3 class="card-title">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="new">
                            <i class="fas fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fas fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button>
                    </h3>
                </div>

                <!-- /.card-header -->
                <div class="card-body form table-responsive" id="users-list">
                    <table id="table-users" class="table table-bordered table-striped dt-responsive nowrap" style="width:100%">
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo langHdl('user_login'); ?></th>
                            <th><?php echo langHdl('name'); ?></th>
                            <th><?php echo langHdl('lastname'); ?></th>
                            <th><?php echo langHdl('managed_by'); ?></th>
                            <th><?php echo langHdl('functions'); ?></th>
                            <th><i class="fas fa-theater-masks fa-lg fa-fw infotip" title="<?php echo langHdl('privileges'); ?>"></i></th>
                            <th><i class="fas fa-code-branch fa-lg fa-fw infotip" title="<?php echo langHdl('can_create_root_folder'); ?>"></i></th>
                            <th><i class="fas fa-hand-holding-heart fa-lg fa-fw infotip" title="<?php echo langHdl('enable_personal_folder'); ?>"></i></th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- USER FORM -->
    <div class="row hidden extra-form" id="row-form">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('user_definition'); ?></h3>
                </div>
                
                <!-- /.card-header -->
                <!-- form start -->
                <form role="form" id="form-user">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="form-name"><?php echo langHdl('name'); ?></label>
                                    <input type="text" class="form-control clear-me required build-login track-change" id="form-name">
                                </div>
                                <div class="form-group">
                                    <label for="form-lastname"><?php echo langHdl('lastname'); ?></label>
                                    <input type="text" class="form-control clear-me required build-login track-change" id="form-lastname">
                                </div>
                                <div class="form-group">
                                    <label for="form-login"><?php echo langHdl('login'); ?></label>
                                    <input type="text" class="form-control clear-me required build-login track-change" id="form-login">
                                    <input type="hidden" id="form-login-conform" value="0">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="form-email"><?php echo langHdl('email'); ?></label>
                                    <input type="email" class="form-control clear-me required track-change" id="form-email">
                                </div>
                                <div class="form-group">
                                    <label for="form-password"><?php echo langHdl('password'); ?></label>
                                    <div class="input-group mb-0">
                                        <input type="password" class="form-control clear-me required infotip track-change" id="form-password">
                                        <div class="input-group-append">
                                            <span class="input-group-text p-1"><div id="form-password-strength"></div></span>
                                            <button class="btn btn-outline-secondary btn-no-click infotip" id="button-password-generate" title="<?php echo langHdl('pw_generate'); ?>"><i class="fas fa-random"></i></button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="form-password-complex" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="form-confirm"><?php echo langHdl('confirm'); ?></label>
                                    <input type="password" class="form-control clear-me required" id="form-confirm">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="form-login" class="mr-2"><?php echo langHdl('privileges'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-admin">
                            <label class="form-check-label mr-2 pointer" for="privilege-admin"><?php echo langHdl('administrator'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-hr">
                            <label class="form-check-label mr-2 pointer" for="privilege-hr"><?php echo langHdl('super_manager'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-manager">
                            <label class="form-check-label mr-2 pointer" for="privilege-manager"><?php echo langHdl('manager'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue track-change" name="privilege" id="privilege-user">
                            <label class="form-check-label mr-2 pointer" for="privilege-user"><?php echo langHdl('user'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue track-change" name="privilege" id="privilege-ro">
                            <label class="form-check-label mr-2 pointer" for="privilege-ro"><?php echo langHdl('read_only'); ?></label>
                        </div>
                        <div class="form-group">
                            <label for="form-roles"><?php echo langHdl('roles'); ?></label>
                            <select id="form-roles" class="form-control form-item-control select2 no-root required track-change" style="width:100%;" multiple="multiple">
                                <?php echo $optionsRoles; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form-managedby"><?php echo langHdl('managed_by'); ?></label>
                            <select id="form-managedby" class="form-control form-item-control select2 no-root required track-change" style="width:100%;">
                                <?php echo $optionsManagedBy; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form-auth"><?php echo langHdl('authorized_groups'); ?></label>
                            <select id="form-auth" class="form-control form-item-control select2 no-root track-change" style="width:100%;" multiple="multiple">
                                <?php echo $foldersList; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form-forbid"><?php echo langHdl('forbidden_groups'); ?></label>
                            <select id="form-forbid" class="form-control form-item-control select2 no-root track-change" style="width:100%;" multiple="multiple">
                                <?php echo $foldersList; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form-forbid"><?php echo langHdl('special'); ?></label>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-root-folder">
                            <label class="form-check-label mr-2" for="form-create-root-folder"><?php echo langHdl('can_create_root_folder'); ?></label>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-personal-folder">
                            <label class="form-check-label mr-2" for="form-create-personal-folder"><?php echo langHdl('enable_personal_folder_for_this_user'); ?></label>
                        </div>
                        <div class="form-group" id="group-create-special-folder">
                            <input type="checkbox" class="form-check-input form-control flat-blu track-changee" id="create-special-folder">
                            <label class="form-check-label mr-2" for="create-special-folder"><?php echo langHdl('auto_create_folder_role'); ?></label>
                            <input type="text" class="form-control clear-me" id="form-special-folder" disabled="true" placeholder="<?php echo langHdl('label'); ?>">
                        </div>
                        <div class="form-group" id="group-form-user-disabled">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-user-disabled">
                            <label class="form-check-label mr-2" for="form-user-disabled"><?php echo langHdl('user_is_disabled'); ?></label>

                            <div class="alert alert-warning mt-2 hidden" id="group-delete-user">
                                <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-delete-user-confirm">
                                <label class="form-check-label mr-2" for="form-delete-user-confirm"><?php echo langHdl('delete_user'); ?></label>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </form>
                    
                <div class="card-footer">
                    <button type="button" class="btn btn-primary tp-action" data-action="submit"><?php echo langHdl('submit'); ?></button>
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- USER LOGS -->
    <div class="row hidden extra-form" id="row-logs">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('logs_for_user'); ?> <span id="row-logs-title"></span></h3>
                </div>
                
                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body form" id="user-logs">
                    <table id="table-logs" class="table table-striped" style="width:100%">
                        <thead>
                        <tr>
                            <th><?php echo langHdl('date'); ?></th>
                            <th><?php echo langHdl('activity'); ?></th>
                            <th><?php echo langHdl('label'); ?></th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
                    
                <div class="card-footer">
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

<!-- USER VISIBLE FOLDERS -->
<div class="row hidden extra-form" id="row-folders">
    <div class="col-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><?php echo langHdl('access_rights_for_user'); ?> <span id="row-folders-title"></span></h3>
            </div>
            
            <!-- /.card-header -->
            <!-- table start -->
            <div class="card-body" id="row-folders-results">
                
            </div>
                
            <div class="card-footer">
                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>
</section>