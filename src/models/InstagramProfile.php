<?php

namespace app\instagram\models;

use app\social\models\SocialMediaProfile;

class InstagramProfile extends SocialMediaProfile
{
    public static $properties = [
        'id' => [
            'type' => 'number',
            'admin_hidden_property' => true
        ],
        'username' => [
            'type' => 'string',
            'admin_html' => '<a href="http://instagram.com/{username}" target="_blank">{username}</a>',
            'searchable' => true
        ],
        'name' => [
            'type' => 'string',
            'searchable' => true
        ],
        'access_token' => [
            'type' => 'password',
            'admin_hidden_property' => true
        ],
        'profile_picture' => [
            'type' => 'string',
            'null' => true,
            'admin_html' => '<a href="{profile_picture}" target="_blank"><img src="{profile_picture}" alt="Profile Picture" class="img-circle" /></a>',
            'admin_truncate' => false,
            'admin_hidden_property' => true
        ],
        'bio' => [
            'type' => 'string',
            'null' => true,
            'admin_nowrap' => false,
            'admin_hidden_property' => true
        ],
        'website' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true
        ],
        'followers_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true
        ],
        'follows_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true
        ],
        'media_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true
        ],
        // the last date the profile was refreshed from instagram
        'last_refreshed' => [
            'type' => 'date',
            'admin_hidden_property' => true
        ],
    ];

    public function userPropertyForProfileId()
    {
        return 'instagram_id';
    }

    public function apiPropertyMapping()
    {
        return [
            'username' => 'username',
            'name' => 'full_name',
            'profile_picture' => 'profile_picture',
            'website' => 'website',
            'bio' => 'bio',
            'followers_count' => 'count.followed_by',
            'follows_count' => 'count.follows',
            'media_count' => 'count.media' ];
    }

    public function daysUntilStale()
    {
        return 7;
    }

    public function numProfilesToRefresh()
    {
        return 180;
    }

    public function url()
    {
        $username = $this->username;

        return ($username) ? 'http://instagram.com/' . $username : '';
    }

    public function profilePicture($size = 80)
    {
        return $this->profile_picture;
    }

    public function useAccessToken()
    {
        $this->app['instagram']->setAccessToken($this->access_token);

        return $this;
    }

    public function isLoggedIn()
    {
        $this->useAccessToken();

        try {
            return is_array($this->app['instagram']->Users->Info($this->_id)->data);
        } catch (\Exception $e) {
            // TODO we can be more thorough
            // by checking for error_type=OAuthAccessTokenError on a valid API call
            $this->app['logger']->error($e);

            return false;
        }
    }

    public function getProfileFromApi()
    {
        $this->useAccessToken();

        try {
            $profile = $this->app['instagram']->Users->Info($this->_id)->data;
        } catch (\Exception $e) {
            // TODO we can be more thorough
            // by checking for error_type=OAuthAccessTokenError on a valid API call
            $this->app['logger']->error($e);

            return false;
        }

        if (!is_array($profile) || count($profile) == 0)
            return false;

        return $profile;
    }
}
