<?php

namespace app\instagram;

use infuse\View;
use Instaphp\Instaphp;

use app\users\models\User;
use app\instagram\models\InstagramProfile;

class Controller
{
    use \InjectApp;

    public static $properties = [
        'models' => [ 'InstagramProfile' ],
        'routes' => [
            'get /instagram/connect' => 'connect',
            'get /instagram/callback' => 'callback',
            'post /instagram/disconnect' => 'disconnect'
        ]
    ];

    public static $scaffoldAdmin;

    public function middleware($req, $res)
    {
        $this->app['instagram'] = function ($c) {
            return new Instaphp($c['config']->get('instagram'));
        };
    }

    public function connect($req, $res)
    {
        // generate forceLogin redirect_uri
        if ($req->query('forceLogin'))
            $this->app['config']->set('instagram.redirect_uri',
                $this->app['config']->get('instagram.redirect_uri') . '?forceLogin=t');

        $res->redirect($this->app['instagram']->getOauthUrl());
    }

    public function disconnect($req, $res)
    {
        $currentUser = $this->app[ 'user' ];

        if ($currentUser->isLoggedIn() || $currentUser->instagramConnected())
            $currentUser->set('instagram_id', null);

        $redir = '/profile';
        if ($req->query('r'))
            $redir = $req->query('r');

        $res->redirect($redir);
    }

    public function callback($req, $res)
    {
        if ($req->query('error_reason'))
            return $res->redirect('/');

        // generate forceLogin redirect_uri
        if ($req->query('forceLogin'))
            $this->app['config']->set('instagram.redirect_uri',
                $this->app['config']->get('instagram.redirect_uri') . '?forceLogin=t');

        $instagram = $this->app['instagram'];

        /* authenticate the user with the instagram API */

        $authenticatedUser = false;
        try {
            if ($instagram->Users->Authorize($req->query('code'))) {
                $authenticatedUser = $instagram->Users->getCurrentUser();
            }
        } catch ( \Exception $e ) {
            $this->app[ 'logger' ]->error( $e );
        }

        if (!$authenticatedUser) {
            $this->app['errors']->push( [
                'context' => 'user.login',
                'error' => 'invalid_token',
                'message' => 'Instagram: Login error. Please try again.'
            ] );

            $usersController = new \app\users\Controller($this->app);

            return $usersController->loginForm($req, $res);
        }

        /* fetch the user's full profile */

        try {
            $user_profile = $instagram->Users->Info($authenticatedUser['id'])->data;
        } catch (\Exception $e) {
            $this->app['logger']->error($e);

            return $res->setCode(500);
        }

        /* log the user in or kick off signup */

        $currentUser = $this->app['user'];

        $iid = $user_profile['id'];

        // generate parameters to update profile
        $profileUpdateArray = [
            'id' => $iid,
            'access_token' => $instagram->getAccessToken() ];

        // instagram id matches existing user?
        $user = User::findOne([
            'where' => [
                'instagram_id' => $iid ]]);

        if ($user) {
            // check if we are dealing with a temporary user
            if (!$user->isTemporary()) {
                if ($user->id() != $currentUser->id()) {
                    if ($req->query('forceLogin') || !$currentUser->isLoggedIn()) {
                        // log the user in
                        $this->app['auth']->signInUser($user->id(), 'instagram');
                    } else {
                        // inform the user that the instagram account they are trying to
                        // connect belongs to someone else
                        return new View('switchingAccounts/instagram.tpl', [
                            'title' => 'Switch accounts?',
                            'otherUser' => $user,
                            'otherProfile' => $user->instagramProfile() ]);
                    }
                }

                $profile = new InstagramProfile($iid);

                // create or update the profile
                if($profile->exists())
                    $profile->set($profileUpdateArray);
                else {
                    $profile = new InstagramProfile();
                    $profile->create($profileUpdateArray);
                }

                // refresh profile from API
                $profile->refreshProfile($user_profile);

                return $this->finalRedirect($req, $res);
            } else {
                // show finish signup screen
                $req->setSession('iid', $iid);

                return $res->redirect('/signup/finish');
            }
        }

        if ($currentUser->isLoggedIn()) {
            // add to current user's account
            $currentUser->set('instagram_id', $iid);
        } else {
            // save this for later
            $req->setSession('iid', $iid);
        }

        $profile = new InstagramProfile($iid);

        // create or update the profile
        if ($profile->exists())
            $profile->set($profileUpdateArray);
        else {
            // create profile
            $profile = new InstagramProfile();
            $profile->create($profileUpdateArray);
        }

        // refresh profile from API
        $profile->refreshProfile($user_profile);

        // get outta here
        if ($currentUser->isLoggedIn())
            $this->finalRedirect($req, $res);
        else
            $res->redirect('/signup/finish');
    }

    private function finalRedirect($req, $res)
    {
        if ( $redirect = $req->cookies('redirect')) {
            $req->setCookie('redirect', '', time() - 86400, '/');
            $res->redirect($redirect);
        } else
            $res->redirect('/profile');
    }

    function refreshProfiles()
    {
        return InstagramProfile::refreshProfiles();
    }
}
