<?php
App::uses('AppModel', 'Model');
App::uses('ConnectionManager', 'Model');
App::uses('Sanitize', 'Utility');

class Bruteforce extends AppModel
{
    public function insert($username)
    {
        $this->Log = ClassRegistry::init('Log');
        $this->Log->create();
        $ip = $this->_remoteIp();
        $expire = Configure::check('SecureAuth.expire') ? Configure::read('SecureAuth.expire') : 300;
        $amount = Configure::check('SecureAuth.amount') ? Configure::read('SecureAuth.amount') : 5;
        $expire = time() + $expire;
        $expire = date('Y-m-d H:i:s', $expire);
        $bruteforceEntry = array(
            'ip' => $ip,
            'username' => trim(strtolower($username)),
            'expire' => $expire
        );
        $this->save($bruteforceEntry);
        $title = 'Failed login attempt using username ' . $username . ' from IP: ' . $ip . '.';
        if ($this->isBlocklisted($username)) {
            $title .= 'This has tripped the bruteforce protection after  ' . $amount . ' failed attempts. The user is now blocklisted for ' . $expire . ' seconds.';
        }
        $log = array(
                'org' => 'SYSTEM',
                'model' => 'User',
                'model_id' => 0,
                'email' => $username,
                'action' => 'login_fail',
                'title' => $title
        );
        $this->Log->save($log);
    }

    public function clean()
    {
        $expire = date('Y-m-d H:i:s', time());
        if ($this->isMysql()) {
            $sql = 'DELETE FROM bruteforces WHERE `expire` <= "' . $expire . '";';
        } else {
            $sql = 'DELETE FROM bruteforces WHERE expire <= \'' . $expire . '\';';
        }
        $this->query($sql);
    }

    public function isBlocklisted($username)
    {
        // first remove old expired rows
        $this->clean();
        // count
        $ip = $this->_remoteIp();
        $params = array(
            'conditions' => array(
            'Bruteforce.ip' => $ip,
            'LOWER(Bruteforce.username)' => trim(strtolower($username)))
        );
        $count = $this->find('count', $params);
        $amount = Configure::check('SecureAuth.amount') ? Configure::read('SecureAuth.amount') : 5;
        if ($count >= $amount) {
            return true;
        } else {
            return false;
        }
    }
}
