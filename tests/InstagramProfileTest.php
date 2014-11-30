<?php

use infuse\Database;
use app\instagram\models\InstagramProfile;

class InstagramProfileTest extends \PHPUnit_Framework_TestCase
{
    public static $profile;

    public static function setUpBeforeClass()
    {
        Database::delete('InstagramProfiles', [ 'id' => 1 ]);
    }

    public static function tearDownAfterClass()
    {
        if (self::$profile) {
            self::$profile->grantAllPermissions();
            self::$profile->delete();
        }
    }

    public function testUserPropertyForProfileId()
    {
        $profile = new InstagramProfile();
        $this->assertEquals('instagram_id', $profile->userPropertyForProfileId());
    }

    public function testApiPropertyMapping()
    {
        $profile = new InstagramProfile();
        $expected = [
            'username' => 'username',
            'name' => 'full_name',
            'profile_picture' => 'profile_picture',
            'website' => 'website',
            'bio' => 'bio',
            'followers_count' => 'count.followed_by',
            'follows_count' => 'count.follows',
            'media_count' => 'count.media',
        ];
        $this->assertEquals($expected, $profile->apiPropertyMapping());
    }

    public function testDaysUntilStale()
    {
        $profile = new InstagramProfile();
        $this->assertEquals(7, $profile->daysUntilStale());
    }

    public function testNumProfilesToRefresh()
    {
        $profile = new InstagramProfile();
        $this->assertEquals(180, $profile->numProfilesToRefresh());
    }

    public function testUrl()
    {
        $profile = new InstagramProfile();
        $profile->username = 'jaredtking';
        $this->assertEquals('http://instagram.com/jaredtking', $profile->url());
    }

    public function testProfilePicture()
    {
        $profile = new InstagramProfile();
        $profile->profile_picture = 'profile_picture';
        $this->assertEquals('profile_picture', $profile->profilePicture());
    }

    public function testIsLoggedIn()
    {
        $profile = new InstagramProfile(100);
        $profile->access_token = 'access_token';

        $app = TestBootstrap::app();
        $instagram = Mockery::mock();
        $instagram->shouldReceive('setAccessToken')->withArgs(['access_token'])->once();
        $instagram->Users = Mockery::mock();
        $result = new stdClass();
        $result->data = ['ok' => true];
        $instagram->Users->shouldReceive('Info')->withArgs([100])->andReturn($result)->once();
        $app['instagram'] = $instagram;

        $this->assertTrue($profile->isLoggedIn());
    }

    public function testIsNotLoggedIn()
    {
        $profile = new InstagramProfile(100);
        $profile->access_token = 'access_token';

        $app = TestBootstrap::app();
        $instagram = Mockery::mock();
        $instagram->shouldReceive('setAccessToken')->withArgs(['access_token'])->once();
        $instagram->Users = Mockery::mock();
        $instagram->Users->shouldReceive('Info')->withArgs([100])->andThrow(new Exception());
        $app['instagram'] = $instagram;

        // $logger = Mockery::mock();
        // $logger->shouldReceive('error')->once();
        // $app['logger'] = $logger;
        // $profile->injectApp($app);

        $this->assertFalse($profile->isLoggedIn());
    }

    public function testCreate()
    {
        self::$profile = new InstagramProfile();
        $this->assertTrue(self::$profile->create([
            'id' => 1,
            'name' => 'Jared',
            'username' => 'jaredtking',
            'profile_picture' => 'profile_picture',
            'access_token' => 'test', ]));
        $this->assertGreaterThan(0, self::$profile->last_refreshed);
    }

    /**
     * @depends testCreate
     */
    public function testEdit()
    {
        sleep(1);
        $oldTime = self::$profile->last_refreshed;

        self::$profile->grantAllPermissions();
        self::$profile->set([
            'name' => 'Test', ]);

        $this->assertNotEquals($oldTime, self::$profile->last_refreshed);
    }

    /**
     * @depends testCreate
     */
    public function testRefreshProfile()
    {
        $response = [
            'username' => 'j',
            'full_name' => 'Some other Jared',
            'website' => 'jaredtking.com',
            'profile_picture' => 'http://jaredtking.com/me.jpg',
            'count' => [
                'followed_by' => 100,
                'follows' => 123,
                'media' => 150, ], ];

        $app = TestBootstrap::app();
        $instagram = Mockery::mock();
        $instagram->shouldReceive('setAccessToken')->withArgs(['test'])->once();
        $instagram->Users = Mockery::mock();
        $result = new stdClass();
        $result->data = $response;
        $instagram->Users->shouldReceive('Info')->withArgs([1])->andReturn($result)->once();
        $app['instagram'] = $instagram;

        $this->assertTrue(self::$profile->refreshProfile());

        $expected = [
            'id' => '1',
            'username' => 'j',
            'name' => 'Some other Jared',
            'access_token' => 'test',
            'profile_picture' => 'http://jaredtking.com/me.jpg',
            'website' => 'jaredtking.com',
            'bio' => null,
            'followers_count' => 100,
            'follows_count' => 123,
            'media_count' => 150, ];

        $profile = self::$profile->toArray([ 'last_refreshed', 'created_at', 'updated_at' ]);

        $this->assertEquals($expected, $profile);
    }

    /**
     * @depends testRefreshProfile
     */
    public function testRefreshProfiles()
    {
        $response = [
            'username' => 'j',
            'full_name' => 'Some other Jared',
            'website' => 'jaredtking.com',
            'profile_picture' => 'http://jaredtking.com/me.jpg',
            'count' => [
                'followed_by' => 100,
                'follows' => 123,
                'media' => 150, ], ];

        $app = TestBootstrap::app();
        $instagram = Mockery::mock();
        $instagram->shouldReceive('setAccessToken')->withArgs(['test'])->once();
        $instagram->Users = Mockery::mock();
        $result = new stdClass();
        $result->data = $response;
        $instagram->Users->shouldReceive('Info')->withArgs([1])->andReturn($result)->once();
        $app['instagram'] = $instagram;

        $t = strtotime('-1 year');
        self::$profile->grantAllPermissions();
        self::$profile->set('last_refreshed', $t);

        $this->assertTrue(InstagramProfile::refreshProfiles());

        self::$profile->load();
        $this->assertGreaterThan($t, self::$profile->last_refreshed);
    }
}
