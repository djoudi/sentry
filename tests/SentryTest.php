<?php
/**
 * Part of the Sentry Package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the 3-clause BSD License.
 *
 * This source file is subject to the 3-clause BSD License that is
 * bundled with this package in the LICENSE file.  It is also available at
 * the following URL: http://www.opensource.org/licenses/BSD-3-Clause
 *
 * @package    Sentry
 * @version    2.0
 * @author     Cartalyst LLC
 * @license    BSD License (3-clause)
 * @copyright  (c) 2011 - 2013, Cartalyst LLC
 * @link       http://cartalyst.com
 */

use Mockery as m;
use Cartalyst\Sentry\Sentry;
use Cartalyst\Sentry\Users\UserNotFoundException;

class SentryTest extends PHPUnit_Framework_TestCase {

	protected $hasher;

	protected $session;

	protected $cookie;

	protected $groupProvider;

	protected $userProvider;

	protected $throttleProvider;

	protected $sentry;

	/**
	 * Setup resources and dependencies.
	 *
	 * @return void
	 */
	public function setUp()
	{
		$this->hasher           = m::mock('Cartalyst\Sentry\Hashing\HasherInterface');
		$this->session          = m::mock('Cartalyst\Sentry\Sessions\SessionInterface');
		$this->cookie           = m::mock('Cartalyst\Sentry\Cookies\CookieInterface');
		$this->groupProvider    = m::mock('Cartalyst\Sentry\Groups\ProviderInterface');
		$this->userProvider     = m::mock('Cartalyst\Sentry\Users\ProviderInterface');
		$this->throttleProvider = m::mock('Cartalyst\Sentry\Throttling\ProviderInterface');

		$this->sentry = new Sentry(
			$this->hasher,
			$this->session,
			$this->cookie,
			$this->groupProvider,
			$this->userProvider,
			$this->throttleProvider
		);
	}

	/**
	 * Close mockery.
	 * 
	 * @return void
	 */
	public function tearDown()
	{
		m::close();
	}

	/**
	 * @expectedException Cartalyst\Sentry\Users\UserNotActivatedException
	 */
	public function testLoggingInUnactivatedUser()
	{
		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(false);
		$user->shouldReceive('getUserLogin')->once()->andReturn('foo');

		$this->sentry->login($user);
	}

	public function testLoggingInUser()
	{
		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(true);
		$user->shouldReceive('getUserLogin')->once()->andReturn('foo');
		$user->shouldReceive('createPersistCode')->once()->andReturn('persist_code');

		$this->session->shouldReceive('put')->with(array('foo', 'persist_code'))->once();

		$this->sentry->login($user);
	}

	/**
	 * @expectedException Cartalyst\Sentry\Users\LoginRequiredException
	 */
	public function testAuthenticatingUserWhenLoginIsNotProvided()
	{
		$credentials = array();

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$user->shouldReceive('getUserLoginName')->once()->andReturn('email');

		$this->sentry->authenticate($credentials);
	}

	/**
	 * @expectedException Cartalyst\Sentry\Users\PasswordRequiredException
	 */
	public function testAuthenticatingUserWhenPasswordIsNotProvided()
	{
		$credentials = array(
			'email' => 'foo@bar.com',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$user->shouldReceive('getUserLoginName')->once()->andReturn('email');

		$this->sentry->authenticate($credentials);
	}

	/**
	 * @expectedException Cartalyst\Sentry\Users\UserNotFoundException
	 */
	public function testAuthenticatingUserWhereTheUserDoesNotExist()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(false);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$user->shouldReceive('getUserLoginName')->once()->andReturn('email');

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andThrow(new UserNotFoundException);

		$this->sentry->authenticate($credentials);
	}

	/**
	 * @expectedException Cartalyst\Sentry\Users\UserNotFoundException
	 */
	public function testAuthenticatingUserWhereTheUserDoesNotExistWithThrottling()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$throttle = m::mock('Cartalyst\Sentry\Throttling\ThrottleInterface');
		$throttle->shouldReceive('addLoginAttempt');

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserLogin')->once()->with('foo@bar.com')->andReturn($throttle);

		$emptyUser = m::mock('Caralyst\Sentry\Users\UserInterface');
		$emptyUser->shouldReceive('getUserLoginName')->once()->andReturn('email');
		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser);

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andThrow(new UserNotFoundException);
		$this->sentry->authenticate($credentials);
	}

	public function testAuthenticatingUser()
	{
		$this->sentry = m::mock('Cartalyst\Sentry\Sentry[login]');
		$this->sentry->__construct(
			$this->hasher,
			$this->session,
			$this->cookie,
			$this->groupProvider,
			$this->userProvider,
			$this->throttleProvider
		);

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(false);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$user->shouldReceive('getUserLoginName')->once()->andReturn('email');

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));

		$user->shouldReceive('clearResetPassword')->once();

		$this->sentry->shouldReceive('login')->with($user, false)->once();
		$this->sentry->authenticate($credentials);
	}

	public function testAuthenticatingUserWithThrottling()
	{
		$this->sentry = m::mock('Cartalyst\Sentry\Sentry[login]');
		$this->sentry->__construct(
			$this->hasher,
			$this->session,
			$this->cookie,
			$this->groupProvider,
			$this->userProvider,
			$this->throttleProvider
		);

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$throttle = m::mock('Cartalyst\Sentry\Throttling\ThrottleInterface');
		$throttle->shouldReceive('check')->once();
		$throttle->shouldReceive('clearLoginAttempts')->once();

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserId')->with(123)->once()->andReturn($throttle);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser = m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$emptyUser->shouldReceive('getUserLoginName')->once()->andReturn('email');

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));

		$user->shouldReceive('getUserId')->once()->andReturn(123);
		$user->shouldReceive('clearResetPassword')->once();

		$this->sentry->shouldReceive('login')->with($user, false)->once();
		$this->sentry->authenticate($credentials);
	}

	public function testAuthenticatingUserAndRemembering()
	{
		$this->sentry = m::mock('Cartalyst\Sentry\Sentry[authenticate]');

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->sentry->shouldReceive('authenticate')->with($credentials, true)->once();
		$this->sentry->authenticateAndRemember($credentials);
	}

	public function testCheckLoggingOut()
	{
		$this->sentry->setUser(m::mock('Cartalyst\Sentry\Users\UserInterface'));
		$this->session->shouldReceive('forget')->once();
		$this->cookie->shouldReceive('forget')->once();
		$this->sentry->logout();
		$this->assertNull($this->sentry->getUser());
	}

	public function testCheckingUserWhenUserIsSetAndActivated()
	{
		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->sentry->setUser($user);
		$this->assertInstanceOf('Cartalyst\Sentry\Users\UserInterface', $this->sentry->check());
	}

	public function testCheckingUserWhenUserIsSetAndNotActivated()
	{
		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(false);

		$this->sentry->setUser($user);
		$this->assertFalse($this->sentry->check());
	}

	public function testCheckingUserChecksSessionFirst()
	{
		$this->session->shouldReceive('get')->once()->andReturn(array('foo', 'persist_code'));
		$this->cookie->shouldReceive('get')->never();

		$this->userProvider->shouldReceive('findByLogin')->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));

		$user->shouldReceive('checkPersistCode')->with('persist_code')->once()->andReturn(true);
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->assertInstanceOf('Cartalyst\Sentry\Users\UserInterface', $this->sentry->check());
	}

	public function testCheckingUserChecksSessionFirstAndThenCookie()
	{
		$this->session->shouldReceive('get')->once();
		$this->cookie->shouldReceive('get')->once()->andReturn(array('foo', 'persist_code'));

		$this->userProvider->shouldReceive('findByLogin')->andReturn($user = m::mock('Cartalyst\Sentry\Users\UserInterface'));

		$user->shouldReceive('checkPersistCode')->with('persist_code')->once()->andReturn(true);
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->assertInstanceOf('Cartalyst\Sentry\Users\UserInterface', $this->sentry->check());
	}

	public function testCheckingUserReturnsFalseIfNoArrayIsReturned()
	{
		$this->session->shouldReceive('get')->once()->andReturn('we_should_never_return_a_string');

		$this->assertFalse($this->sentry->check());
	}

	public function testCheckingUserReturnsFalseIfIncorrectArrayIsReturned()
	{
		$this->session->shouldReceive('get')->once()->andReturn(array('we', 'should', 'never', 'have', 'more', 'than', 'two'));

		$this->assertFalse($this->sentry->check());
	}

	public function testCheckingUserWhenNothingIsFound()
	{
		$this->session->shouldReceive('get')->once()->andReturn(null);

		$this->cookie->shouldReceive('get')->once()->andReturn(null);

		$this->assertFalse($this->sentry->check());
	}

	public function testRegisteringUser()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'sdf_sdf',
		);

		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('getActivationCode')->never();
		$user->shouldReceive('attemptActivation')->never();
		$user->shouldReceive('isActivated')->once()->andReturn(false);

		$this->userProvider->shouldReceive('create')->with($credentials)->once()->andReturn($user);

		$this->assertEquals($user, $registeredUser = $this->sentry->register($credentials));
		$this->assertFalse($registeredUser->isActivated());
	}

	public function testRegisteringUserWithActivationDone()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'sdf_sdf',
		);

		$user = m::mock('Cartalyst\Sentry\Users\UserInterface');
		$user->shouldReceive('getActivationCode')->once()->andReturn('activation_code_here');
		$user->shouldReceive('attemptActivation')->with('activation_code_here')->once();
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->userProvider->shouldReceive('create')->with($credentials)->once()->andReturn($user);

		$this->assertEquals($user, $registeredUser = $this->sentry->register($credentials, true));
		$this->assertTrue($registeredUser->isActivated());
	}

}