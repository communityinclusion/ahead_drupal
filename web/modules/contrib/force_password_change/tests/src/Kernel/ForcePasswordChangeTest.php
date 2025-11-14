<?php

declare(strict_types=1);

namespace Drupal\Tests\force_password_change\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface;
use Drupal\force_password_change\Service\ForcePasswordChangeService;
use Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Test force password change service.
 */
#[CoversClass(ForcePasswordChangeService::class)]
#[Group('force_password_change')]
final class ForcePasswordChangeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'force_password_change',
    'user',
  ];

  #[DataProvider('checkForceProvider')]
  public function testCheckForForce(mixed $expected, array $values): void {
    $mapper = $this->prophesize(ForcePasswordChangeMapperInterface::class);
    $mapper->getUserCreatedTime(Argument::type('int'))->willReturn(1000000);
    $currentUser = $this->prophesize(AccountProxyInterface::class);
    $currentUser->id()->willReturn(1);
    $currentUser->getRoles()->willReturn(['authenticated']);
    $config = $this->prophesize(ImmutableConfig::class);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('force_password_change.settings')->willReturn($config);
    $userData = $this->prophesize(UserDataInterface::class);
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn(1000101);

    foreach ($values as $property => $propertyValues) {
      foreach ($propertyValues as $key => $value) {
        $double = NULL;
        if ($property === 'configFactory') {
          $config->get($key)->willReturn($value);
          $configFactory->get('force_password_change.settings')->willreturn($config->reveal());
        }
        elseif (array_key_exists('arguments', $value)) {
          $double = ${$property}->{$value['method']}(...$value['arguments']);
        }
        else {
          $double = ${$property}->{$value['method']}();
        }
        if (\is_array($value) && \array_key_exists('return', $value)) {
          $double->willReturn($value['return']);
        }
        else {
          $double?->willReturn();
        }
      }
    }
    $service = new ForcePasswordChangeService(
      $mapper->reveal(),
      $currentUser->reveal(),
      $configFactory->reveal(),
      $userData->reveal(),
      $time->reveal(),
    );

    $this->assertEquals($expected, $service->checkForForce());
  }

  public static function checkForceProvider(): array {
    $return = [];

    $return['pending_force'] = [
      'expected' => 'admin_forced',
      'values' => [
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => TRUE,
          ],
        ],
      ],
    ];
    $return['expired'] = [
      'expected' => 'expired',
      'values' => [
        'configFactory' => [
          'expire_password' => TRUE,
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
          'getLastChange' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'last_change'],
            'return' => 1000000,
          ],
          'setPendingForce' => [
            'method' => 'set',
            'arguments' => ['force_password_change', 1, 'pending_force', 1],
          ],
        ],
        'mapper' => [
          'getExpiryTimeFromRoles' => [
            'method' => 'getExpiryTimeFromRoles',
            'arguments' => [['authenticated']],
            'return' => 100,
          ],
        ],
      ],
    ];
    $return['recently_changed'] = [
      'expected' => FALSE,
      'values' => [
        'configFactory' => [
          'expire_password' => TRUE,
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
          'getLastChange' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'last_change'],
            'return' => 1000001,
          ],
          'setPendingForce' => [
            'method' => 'set',
            'arguments' => ['force_password_change', 1, 'pending_force', 1],
          ],
        ],
        'mapper' => [
          'getExpiryTimeFromRoles' => [
            'method' => 'getExpiryTimeFromRoles',
            'arguments' => [['authenticated']],
            'return' => 100,
          ],
        ],
      ],
    ];
    $return['recently_expired'] = [
      'expected' => 'expired',
      'values' => [
        'configFactory' => [
          'expire_password' => TRUE,
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
          'getLastChange' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'last_change'],
            'return' => NULL,
          ],
          'setPendingForce' => [
            'method' => 'set',
            'arguments' => ['force_password_change', 1, 'pending_force', 1],
          ],
        ],
        'mapper' => [
          'getExpiryTimeFromRoles' => [
            'method' => 'getExpiryTimeFromRoles',
            'arguments' => [['authenticated']],
            'return' => 99,
          ],
        ],
      ],
    ];
    $return['just_expired'] = [
      'expected' => FALSE,
      'values' => [
        'configFactory' => [
          'expire_password' => TRUE,
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
          'getLastChange' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'last_change'],
            'return' => NULL,
          ],
        ],
        'mapper' => [
          'getExpiryTimeFromRoles' => [
            'method' => 'getExpiryTimeFromRoles',
            'arguments' => [['authenticated']],
            'return' => 101,
          ],
        ],
      ],
    ];
    $return['nearly_expired'] = [
      'expected' => 'expired',
      'values' => [
        'configFactory' => [
          'expire_password' => 'TRUE',
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
          'getLastChange' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'last_change'],
            'return' => NULL,
          ],
          'setPendingForce' => [
            'method' => 'set',
            'arguments' => ['force_password_change', 1, 'pending_force', 1],
          ],
        ],
        'mapper' => [
          'getExpiryTimeFromRoles' => [
            'method' => 'getExpiryTimeFromRoles',
            'arguments' => [['authenticated']],
            'return' => 100,
          ],
        ],
      ],
    ];
    $return['no_track_expire'] = [
      'expected' => FALSE,
      'values' => [
        'configFactory' => [
          'expire_password' => FALSE,
        ],
        'userData' => [
          'getPendingForce' => [
            'method' => 'get',
            'arguments' => ['force_password_change', 1, 'pending_force'],
            'return' => FALSE,
          ],
        ],
      ],
    ];

    return $return;
  }

  public function testGetTextDate(): void {
    $service = $this->container->get('force_password_change.service');
    \assert($service instanceof ForcePasswordChangeServiceInterface);

    $year = $service->getTextDate(31536000);
    $this->assertEquals('year', $year);
    $week = $service->getTextDate(604800);
    $this->assertEquals('week', $week);
    $day = $service->getTextDate(86400);
    $this->assertEquals('day', $day);
    $hour = $service->getTextDate(3600);
    $this->assertEquals('hour', $hour);
    $random = $service->getTextDate(12341234);
    $this->assertEquals('', $random);
  }

}
