<?php

class PingdomGatewayTest extends SapphireTest {

	/**
	 * @var \Acquia\Pingdom\PingdomApi
	 */
	protected $api = null;

	protected $gateway = null;

	public function setUp() {
		parent::setUp();
		// mock the real api
		$this->api = $this->getMock(
			'\Acquia\Pingdom\PingdomApi',
			['request'], // only mock the request method
			["user@test.com", "password", "token"] // constructor arguments
		);

		$this->gateway = new PingdomGateway();
		$this->gateway->setClient($this->api);
	}

	public function testGetUsers() {
		$result = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 578657,
				'name' => 'Test Contact (u)',
			]
		]];

		$this->api->expects($this->once())
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		$contacts = $this->gateway->getNotificationContacts();
		$this->assertInternalType('array', $contacts);
		$this->assertEquals(count($contacts), 1, 'there should be one contact from getAllContacts()');
		$this->assertEquals($contacts[0]->email, 'contact@test.com');
	}

	public function testAddContact() {
		$result = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 578657,
				'name' => 'Test Contact (u)',
			]
		]];

		$newUser = (object) [ 'contact' => (object)[
			'id' => 10961547,
			'name' => 'random user'
		]];

		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		$this->api->expects($this->at(1))
			->method('request')
			->with($this->equalTo('POST'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($newUser));

		$newContact = [
			'name' => 'random user',
			'email' => 'random@silverstripe.com',
		];

		$this->gateway->addOrModifyContact($newContact);
	}

	public function testAddContactNoEmailThrowException() {

		$this->api->expects($this->never())->method('request');

		$this->setExpectedException('RuntimeException');

		$this->gateway->addOrModifyContact(['name' => 'random user']);
	}

	public function testAddContactNoNameUsesEmail() {

		$result = (object) ['contacts' => []];

		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		$newUser = (object) [ 'contact' => (object)[
			'id' => 10961547,
			'name' => 'random@silverstripe.com'
		]];

		// expect that name is set to the email address
		$this->api->expects($this->at(1))
			->method('request')
			->with($this->equalTo('POST'), $this->equalTo('notification_contacts'), $this->equalTo([
				'email' => 'random@silverstripe.com',
				'name' => 'random@silverstripe.com',
			]))
			->will($this->returnValue($newUser));

		$newContact = [
			'email' => 'random@silverstripe.com',
		];

		$this->gateway->addOrModifyContact($newContact);

	}

	public function testUpdateContact() {

		$result = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 578657,
				'name' => 'Test Contact (u)',
			]
		]];

		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		// expect that we are using PUT for modifying existing user
		$this->api->expects($this->at(1))
			->method('request')
			->with($this->equalTo('PUT'), $this->equalTo('notification_contacts/578657'))
			->will($this->returnValue((object)['message' => 'Modification of notification contact was successful!']));

		$contact = [
			'name' => 'Updated Name (u)',
			'email' => 'contact@test.com'
		];

		$this->gateway->addOrModifyContact($contact);
	}

	public function testParamsFromURL() {
		$this->assertEquals($this->gateway->paramsFromURL("https://test.com/endpoint"), [
			"host" => "test.com",
			"url" => '/endpoint',
			"encryption" => true,
		]);

		$this->assertEquals($this->gateway->paramsFromURL("https://test.com//dev/check"), [
			"host" => "test.com",
			"url" => '/dev/check',
			"encryption" => true,
		]);

		$this->assertEquals($this->gateway->paramsFromURL("http://test.nu/endpoint2"), [
			"host" => "test.nu",
			"url" => '/endpoint2',
			"encryption" => false,
		]);

		$this->assertEquals($this->gateway->paramsFromURL("https://test.net/"), [
			"host" => "test.net",
			"url" => '/',
			"encryption" => true,
		]);

		$this->assertEquals($this->gateway->paramsFromURL("https://test.net/"),  [
			"host" => "test.net",
			"url" => '/',
			"encryption" => true,
		]);

		$this->assertEquals($this->gateway->paramsFromURL("ftp://test.net/"), []);

		$this->assertEquals($this->gateway->paramsFromURL("laosdlasdo"), []);

		$this->assertEquals($this->gateway->paramsFromURL("http://test.com/hello?test"), [
			"host" => "test.com",
			"url" => '/hello?test',
			"encryption" => false
		]);
	}

	public function testGetNotificationContact() {
		$result = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 231231,
				'name' => 'Test Contact (u)',
			]
		]];
		$this->api->expects($this->once())
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		$contact = $this->gateway->getNotificationContact(231231);
		$this->assertEquals($contact->id, 231231);
	}

	public function testGetNotificationContactNotFound() {
		$result = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 231231,
				'name' => 'Test Contact (u)',
			]
		]];
		$this->api->expects($this->once())
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($result));

		$contact = $this->gateway->getNotificationContact(10961547);
		$this->assertEquals($contact, false);
	}

	public function testGetCheckURL() {
		$check = (object) [
			'hostname' => 'test.com',
			'type' => (object) [
				'http' => (object) [
					'encryption' => true,
					'url' => '/dev/check/suite'
				]
			],
			'encryption' => true,
		];

		$url = $this->gateway->getCheckURL($check);
		$this->assertEquals($url, 'https://test.com/dev/check/suite');

		$check->type->http->encryption = false;
		$url = $this->gateway->getCheckURL($check);
		$this->assertEquals($url, 'http://test.com/dev/check/suite');
	}

	public function testGetCheckURLNotHTTPCheck() {
		$check = (object) [
			'hostname' => 'test.com',
			'type' => (object) [
				'tcp' => (object) [ 'port' => 80 ]
			],
			'encryption' => true,
		];

		$url = $this->gateway->getCheckURL($check);
		$this->assertEquals($url, false);
	}

	public function testRemoveNotificationContactNoDeletion() {
		$getChecks = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 578657,
				'name' => 'Test Contact (u)',
			]
		]];
		$this->api->expects($this->once())
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($getChecks));

		$result = $this->gateway->removeNotificationContact('test@test.se');
		$this->assertFalse($result);
	}

	public function testRemoveNotificationContact() {
		$getChecks = (object) ['contacts' => [
			(object) [
				'email' => 'contact@test.com',
				'id' => 578657,
				'name' => 'Test Contact (u)',
			]
		]];
		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($getChecks));

		$this->api->expects($this->at(1))
			->method('request')
			->with($this->equalTo('DELETE'), $this->equalTo('notification_contacts/578657'))
			->will($this->returnValue((object) ['message' => 'something got deleted']));

		$result = $this->gateway->removeNotificationContact('contact@test.com');
		$this->assertEquals($result, "something got deleted");
	}

	public function testGetChecks() {
		$getChecks = (object) ['checks' => [
			(object) [
				'id' => 578657,
				'name' => '/dev/check/suite',
				'hostname' => 'test.com',
				'resolution' => 1,
				'type' => 'http',
				'status' => 'UP',
			]
		]];
		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('checks'))
			->will($this->returnValue($getChecks));

		$this->gateway->getChecks();
	}

	public function testAddOrModifyAlertErrorNoName() {
		$this->gateway->addOrModifyAlert(
			'https://test.com/dev/check',
			[ ['email' => 'contact@test.com'] ],
			5,
			false
		);
		$this->assertEquals($this->gateway->getLastError(), "one contact did not have a 'name' defined");
	}

	/**
	 * @covers PingdomGateway::addOrModifyAlert
	 */
	public function testAddOrModifyAlert() {
		$getChecks = (object) ['checks' => [
			(object) [
				'id' => 578657,
				'name' => '/dev/check/suite',
				'hostname' => 'test.com',
				'resolution' => 1,
				'type' => 'http',
				'status' => 'UP',
			]
		]];
		$this->api->expects($this->at(0))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('checks'))
			->will($this->returnValue($getChecks));

		$getCheck = (object) [ 'check' => (object) [
			'id' => 578657,
			'name' => '/dev/check/suite',
			'hostname' => 'test.com',
			'resolution' => 1,
			'type' => (object) [
				'http' => (object) [
					'encryption' => true,
					'url' => '/dev/check/suite'
				]
			],
			'encryption' => true,
			'status' => 'UP',
		]];

		$this->api->expects($this->at(1))
			->method('request')
			->with($this->equalTo('GET'), $this->equalTo('checks/578657'))
			->will($this->returnValue($getCheck));

		$postNotificationContact = (object) [
			'contact' => (object) [
				'id' => 123456,
				'name' => 'contact name',
			]
		];

		$this->api->expects($this->at(2))
			->method('request')
			->with($this->equalTo('POST'), $this->equalTo('notification_contacts'))
			->will($this->returnValue($postNotificationContact));


		$putCheck = (object) [
			'message' => 'silly message'
		];

		$this->api->expects($this->at(3))
			->method('request')
			->with($this->equalTo('PUT'), $this->equalTo('checks/578657'))
			->will($this->returnValue($putCheck));


		$success = $this->gateway->addOrModifyAlert(
			'https://test.com/dev/check/suite',
			[ ['email' => 'contact@test.com', 'name' => 'contact name'] ],
			1,
			false
		);
		$this->assertEquals($this->gateway->getLastError(), null);
		$this->assertTrue($success);
	}
}
