<?php

namespace uc\server\app\control\admin;

use uc\server\app\base\BackendControl as Control;

class AdminControl extends Control
{
    
    protected $msgs = [
        '1' => 'admin_add_succeed',
        '-1' => 'admin_add_succeed',
        '-2' => 'admin_failed',
        '-3' => 'admin_user_nonexistance',
        '-4' => 'admin_config_unwritable',
        '-5' => 'admin_founder_pw_incorrect',
        '-6' => 'admin_pw_incorrect',
        '2' => 'admin_founder_pw_modified',
        '3' => 'admin_privilege_updated'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load('user');
        $this->check_priv();
        if (! $this->user['isfounder'] && ! $this->user['allowadminbadword']) {
            $this->message('no_permission_for_this_module');
        }
    }

    public function onls()
    {
        $status = 0;
        if (! empty($_POST['addname']) && $this->submitcheck()) {
            $addname = getgpc('addname', 'P');
            $this->view->assign('addname', $addname);
            $uid = $this->db->result_first("SELECT uid FROM " . UC_DBTABLEPRE . "members WHERE username='$addname'");
            if ($uid) {
                $adminuid = $this->db->result_first("SELECT uid FROM " . UC_DBTABLEPRE . "admins WHERE username='$addname'");
                if ($adminuid) {
                    $status = - 1;
                } else {
                    $allowadminsetting = getgpc('allowadminsetting', 'P');
                    $allowadminapp = getgpc('allowadminapp', 'P');
                    $allowadminuser = getgpc('allowadminuser', 'P');
                    $allowadminbadword = getgpc('allowadminbadword', 'P');
                    $allowadmincredits = getgpc('allowadmincredits', 'P');
                    $allowadmintag = getgpc('allowadmintag', 'P');
                    $allowadminpm = getgpc('allowadminpm', 'P');
                    $allowadmindomain = getgpc('allowadmindomain', 'P');
                    $allowadmindb = getgpc('allowadmindb', 'P');
                    $allowadminnote = getgpc('allowadminnote', 'P');
                    $allowadmincache = getgpc('allowadmincache', 'P');
                    $allowadminlog = getgpc('allowadminlog', 'P');
                    $this->db->execute("INSERT INTO " . UC_DBTABLEPRE . "admins SET
						uid='$uid',
						username='$addname',
						allowadminsetting='$allowadminsetting',
						allowadminapp='$allowadminapp',
						allowadminuser='$allowadminuser',
						allowadminbadword='$allowadminbadword',
						allowadmincredits='$allowadmincredits',
						allowadmintag='$allowadmintag',
						allowadminpm='$allowadminpm',
						allowadmindomain='$allowadmindomain',
						allowadmindb='$allowadmindb',
						allowadminnote='$allowadminnote',
						allowadmincache='$allowadmincache',
						allowadminlog='$allowadminlog'");
                    $insertid = $this->db->insert_id();
                    if ($insertid) {
                        $this->writelog('admin_add', 'username=' . dhtmlspecialchars($addname));
                        $status = 1;
                    } else {
                        $status = - 2;
                    }
                }
            } else {
                $status = - 3;
            }
        }
        
        if (! empty($_POST['editpwsubmit']) && $this->submitcheck()) {
            $oldpw = getgpc('oldpw', 'P');
            $newpw = getgpc('newpw', 'P');
            $newpw2 = getgpc('newpw2', 'P');
            if (UC_FOUNDERPW == md5(md5($oldpw) . UC_FOUNDERSALT)) {
                $configfile = UC_ROOT . './data/config.inc.php';
                if (! is_writable($configfile)) {
                    $status = - 4;
                } else {
                    if ($newpw != $newpw2) {
                        $status = - 6;
                    } else {
                        $config = file_get_contents($configfile);
                        $salt = substr(uniqid(rand()), 0, 6);
                        $md5newpw = md5(md5($newpw) . $salt);
                        $config = preg_replace("/define\('UC_FOUNDERSALT',\s*'.*?'\);/i", "define('UC_FOUNDERSALT', '$salt');", $config);
                        $config = preg_replace("/define\('UC_FOUNDERPW',\s*'.*?'\);/i", "define('UC_FOUNDERPW', '$md5newpw');", $config);
                        $fp = @fopen($configfile, 'w');
                        @fwrite($fp, $config);
                        @fclose($fp);
                        $status = 2;
                        $this->writelog('admin_pw_edit');
                    }
                }
            } else {
                $status = - 5;
            }
        }
        
        $this->view->assign('status', $status);
        
        if (! empty($_POST['delete'])) {
            $uids = $this->implode(getgpc('delete', 'P'));
            $this->db->execute("DELETE FROM " . UC_DBTABLEPRE . "admins WHERE uid IN ($uids)");
        }
        
        $page = max(1, getgpc('page'));
        $ppp = 15;
        $totalnum = $this->db->result_first("SELECT COUNT(*) FROM " . UC_DBTABLEPRE . "admins");
        $start = $this->page_get_start($page, $ppp, $totalnum);
        $userlist = $this->db->fetch_all("SELECT a.*,m.* FROM " . UC_DBTABLEPRE . "admins a LEFT JOIN " . UC_DBTABLEPRE . "members m USING(uid) LIMIT $start, $ppp");
        $multipage = $this->page($totalnum, $ppp, $page, 'admin.php?m=admin&a=admin');
        if ($userlist) {
            foreach ($userlist as $key => $user) {
                $user['regdate'] = $this->date($user['regdate']);
                $userlist[$key] = $user;
            }
        }
        
        $a = getgpc('a');
        $this->view->assign('a', $a);
        $this->view->assign('multipage', $multipage);
        $this->view->assign('userlist', $userlist);
        $this->view->display('admin_admin');
    }

    function onedit()
    {
        $uid = (int) getgpc('uid');
        $status = 0;
        $admin = $this->load('admin')->find(['uid' => $uid]);
        
        if ($this->submitcheck()) {
            $affected = $this->load('admin')->update(
                    [
                        'allowadminsetting' => getgpc('allowadminsetting', 'P'),
                        'allowadminapp' => getgpc('allowadminapp', 'P'),
                        'allowadminuser' => getgpc('allowadminuser', 'P'),
                        'allowadminbadword' => getgpc('allowadminbadword', 'P'),
                        'allowadmincredits' => getgpc('allowadmincredits', 'P'),
                        'allowadmintag' => getgpc('allowadmintag', 'P'),
                        'allowadminpm' => getgpc('allowadminpm', 'P'),
                        'allowadmindomain' => getgpc('allowadmindomain', 'P'),
                        'allowadmindb' => getgpc('allowadmindb', 'P'),
                        'allowadminnote' => getgpc('allowadminnote', 'P'),
                        'allowadmincache' => getgpc('allowadmincache', 'P'),
                        'allowadminlog' => getgpc('allowadminlog', 'P')
                    ],
                    ['uid' => $uid]
            );
            
            $status = $affected ? 3 : -1;
                
            $this->writelog('admin_priv_edit', 'username=' . dhtmlspecialchars($admin));
            
            $this->message($this->msgs[$status], 'admin.php?m=admin&a=ls');
        }
        
        $this->view->assign('uid', $uid);
        $this->view->assign('admin', $admin);
        $this->view->assign('status', $status);
        $this->view->display('admin_admin');
    }
    
}
