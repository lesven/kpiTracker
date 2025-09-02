<?php

namespace App\Tests\Factory;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\User;
use App\Factory\UserFactory;
use PHPUnit\Framework\TestCase;

class UserFactoryTest extends TestCase
{
    private UserFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new UserFactory();
    }

    public function testCreateRegularUser(): void
    {
        $email = 'test@example.com';
        $firstName = 'John';
        $lastName = 'Doe';

        $user = $this->factory->createRegularUser($email, $firstName, $lastName);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(EmailAddress::class, $user->getEmail());
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());
        $this->assertSame([User::ROLE_USER], $user->getRoles());
    }

    public function testCreateAdmin(): void
    {
        $email = 'admin@example.com';
        $firstName = 'Jane';
        $lastName = 'Admin';

        $user = $this->factory->createAdmin($email, $firstName, $lastName);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(EmailAddress::class, $user->getEmail());
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());
        $this->assertSame([User::ROLE_ADMIN, User::ROLE_USER], $user->getRoles());
    }

    public function testCreateWithRoles(): void
    {
        $email = 'custom@example.com';
        $firstName = 'Custom';
        $lastName = 'User';
        $roles = ['ROLE_CUSTOM', 'ROLE_SPECIAL'];

        $user = $this->factory->createWithRoles($email, $firstName, $lastName, $roles);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(EmailAddress::class, $user->getEmail());
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());
        // User entity automatically adds ROLE_USER, so we expect it to be included
        $expectedRoles = array_merge($roles, [User::ROLE_USER]);
        $this->assertSame($expectedRoles, $user->getRoles());
    }

    public function testCreateByTypeWithAdminFlag(): void
    {
        $email = 'flagged@example.com';
        $firstName = 'Flagged';
        $lastName = 'Admin';

        $user = $this->factory->createByType($email, $firstName, $lastName, true);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());
        $this->assertSame([User::ROLE_ADMIN, User::ROLE_USER], $user->getRoles());
    }

    public function testCreateByTypeWithoutAdminFlag(): void
    {
        $email = 'regular@example.com';
        $firstName = 'Regular';
        $lastName = 'User';

        $user = $this->factory->createByType($email, $firstName, $lastName, false);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());
        $this->assertSame([User::ROLE_USER], $user->getRoles());
    }

    public function testCreateWithEmptyEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->createRegularUser('', 'John', 'Doe');
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testCreateWithInvalidEmailThrowsException(string $invalidEmail): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->createRegularUser($invalidEmail, 'John', 'Doe');
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'invalid format' => ['invalid-email'],
            'missing domain' => ['test@'],
            'missing local' => ['@domain.com'],
            'double @' => ['test@@domain.com'],
            'spaces' => ['test @domain.com'],
        ];
    }

    public function testMultipleUsersHaveIndependentProperties(): void
    {
        $user1 = $this->factory->createRegularUser('user1@example.com', 'John', 'Doe');
        $user2 = $this->factory->createAdmin('user2@example.com', 'Jane', 'Admin');

        $this->assertNotSame($user1, $user2);
        $this->assertNotSame($user1->getEmail(), $user2->getEmail());
        $this->assertSame('user1@example.com', $user1->getEmail()->getValue());
        $this->assertSame('user2@example.com', $user2->getEmail()->getValue());
        $this->assertSame([User::ROLE_USER], $user1->getRoles());
        $this->assertSame([User::ROLE_ADMIN, User::ROLE_USER], $user2->getRoles());
    }

    public function testCreateWithRolesEmptyArray(): void
    {
        $user = $this->factory->createWithRoles('test@example.com', 'Test', 'User', []);

        $this->assertInstanceOf(User::class, $user);
        // User entity automatically adds ROLE_USER, so even with empty array it should be present
        $this->assertSame([User::ROLE_USER], $user->getRoles());
    }

    public function testUserPropertiesAreSetCorrectly(): void
    {
        $email = 'detailed@example.com';
        $firstName = 'First Name With Spaces';
        $lastName = 'Last-Name-With-Hyphens';

        $user = $this->factory->createRegularUser($email, $firstName, $lastName);

        // Test that all properties are properly set
        $this->assertSame($email, $user->getEmail()->getValue());
        $this->assertSame($firstName, $user->getFirstName());
        $this->assertSame($lastName, $user->getLastName());

        // Test that user is properly initialized
        $this->assertNull($user->getId()); // New entity should have no ID
        $this->assertNull($user->getPassword()); // Password not set by factory
        $this->assertNotEmpty($user->getRoles()); // Should have at least one role
    }
}
