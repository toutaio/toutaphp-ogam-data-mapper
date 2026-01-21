<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Cache\EvictionPolicy;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Parsing\XmlMapperParser;

final class XmlMapperParserTest extends TestCase
{
    private Configuration $configuration;

    private XmlMapperParser $parser;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->parser = new XmlMapperParser($this->configuration);
        $this->tempDir = sys_get_temp_dir() . '/ogam_mapper_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testParseFailsWhenFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapper file not found:');

        $this->parser->parse('/nonexistent/mapper.xml');
    }

    public function testParseXmlFailsWithInvalidXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse XML');

        $this->parser->parseXml('this is not valid xml');
    }

    public function testParseXmlFailsWithWrongRootElement(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <wrongElement>
            </wrongElement>
            XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid mapper: root element must be <mapper>');

        $this->parser->parseXml($xml);
    }

    public function testParseXmlFailsWithoutNamespace(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper>
            </mapper>
            XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapper must have a namespace attribute');

        $this->parser->parseXml($xml);
    }

    public function testParseMinimalMapper(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="TestMapper">
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        // No exception means success
        $this->assertTrue(true);
    }

    public function testParseSelectStatement(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findById">
                    SELECT * FROM users WHERE id = #{id}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findById');
        $this->assertNotNull($statement);
        $this->assertSame('findById', $statement->getId());
        $this->assertSame('UserMapper', $statement->getNamespace());
        $this->assertSame(StatementType::SELECT, $statement->getType());
    }

    public function testParseInsertStatement(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <insert id="insertUser">
                    INSERT INTO users (name, email) VALUES (#{name}, #{email})
                </insert>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.insertUser');
        $this->assertNotNull($statement);
        $this->assertSame(StatementType::INSERT, $statement->getType());
    }

    public function testParseUpdateStatement(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <update id="updateUser">
                    UPDATE users SET name = #{name} WHERE id = #{id}
                </update>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.updateUser');
        $this->assertNotNull($statement);
        $this->assertSame(StatementType::UPDATE, $statement->getType());
    }

    public function testParseDeleteStatement(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <delete id="deleteUser">
                    DELETE FROM users WHERE id = #{id}
                </delete>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.deleteUser');
        $this->assertNotNull($statement);
        $this->assertSame(StatementType::DELETE, $statement->getType());
    }

    public function testParseStatementWithResultType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findById" resultType="User">
                    SELECT * FROM users WHERE id = #{id}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findById');
        $this->assertNotNull($statement);
        $this->assertSame('User', $statement->getResultType());
    }

    public function testParseStatementWithResultMap(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <result property="name" column="name"/>
                </resultMap>
                <select id="findById" resultMap="UserResult">
                    SELECT * FROM users WHERE id = #{id}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findById');
        $this->assertNotNull($statement);
        $this->assertSame('UserMapper.UserResult', $statement->getResultMapId());
    }

    public function testParseStatementWithParameterType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <insert id="insertUser" parameterType="User">
                    INSERT INTO users (name) VALUES (#{name})
                </insert>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.insertUser');
        $this->assertNotNull($statement);
        $this->assertSame('User', $statement->getParameterType());
    }

    public function testParseStatementWithUseGeneratedKeys(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <insert id="insertUser" useGeneratedKeys="true" keyProperty="id">
                    INSERT INTO users (name) VALUES (#{name})
                </insert>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.insertUser');
        $this->assertNotNull($statement);
        $this->assertTrue($statement->isUseGeneratedKeys());
        $this->assertSame('id', $statement->getKeyProperty());
    }

    public function testParseStatementWithKeyColumn(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <insert id="insertUser" useGeneratedKeys="true" keyProperty="id" keyColumn="user_id">
                    INSERT INTO users (name) VALUES (#{name})
                </insert>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.insertUser');
        $this->assertNotNull($statement);
        $this->assertSame('user_id', $statement->getKeyColumn());
    }

    public function testParseStatementWithTimeout(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll" timeout="30000">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);
        $this->assertSame(30000, $statement->getTimeout());
    }

    public function testParseStatementWithFetchSize(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll" fetchSize="100">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);
        $this->assertSame(100, $statement->getFetchSize());
    }

    public function testParseStatementWithHydrationArray(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll" hydration="array">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);
        $this->assertSame(Hydration::ARRAY, $statement->getHydration());
    }

    public function testParseStatementWithHydrationScalar(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="count" hydration="scalar">
                    SELECT COUNT(*) FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.count');
        $this->assertNotNull($statement);
        $this->assertSame(Hydration::SCALAR, $statement->getHydration());
    }

    public function testParseStatementWithHydrationObject(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findById" hydration="object">
                    SELECT * FROM users WHERE id = #{id}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findById');
        $this->assertNotNull($statement);
        $this->assertSame(Hydration::OBJECT, $statement->getHydration());
    }

    public function testParseResultMap(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="user_id"/>
                    <result property="name" column="user_name"/>
                    <result property="email" column="user_email"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertSame('UserMapper.UserResult', $resultMap->getId());
        $this->assertSame('User', $resultMap->getType());
        $this->assertCount(1, $resultMap->getIdMappings());
        $this->assertCount(2, $resultMap->getResultMappings());
    }

    public function testParseResultMapWithTypeAlias(): void
    {
        $this->configuration->addTypeAlias('User', 'App\Entity\User');

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertSame('App\Entity\User', $resultMap->getType());
    }

    public function testParseResultMapWithExtends(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="BaseResult" type="User">
                    <id property="id" column="id"/>
                </resultMap>
                <resultMap id="ExtendedResult" type="User" extends="BaseResult">
                    <result property="name" column="name"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.ExtendedResult');
        $this->assertNotNull($resultMap);
        $this->assertSame('UserMapper.BaseResult', $resultMap->getExtends());
    }

    public function testParseResultMapWithAutoMappingDisabled(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User" autoMapping="false">
                    <id property="id" column="id"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertFalse($resultMap->isAutoMapping());
    }

    public function testParseResultMapWithAssociation(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <association property="address" phpType="Address" columnPrefix="addr_">
                        <id property="id" column="id"/>
                        <result property="street" column="street"/>
                    </association>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertCount(1, $resultMap->getAssociations());

        $association = $resultMap->getAssociations()[0];
        $this->assertSame('address', $association->getProperty());
        $this->assertSame('Address', $association->getPhpType());
        $this->assertSame('addr_', $association->getColumnPrefix());
    }

    public function testParseResultMapWithAssociationResultMap(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="AddressResult" type="Address">
                    <id property="id" column="id"/>
                </resultMap>
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <association property="address" phpType="Address" resultMap="AddressResult"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);

        $association = $resultMap->getAssociations()[0];
        $this->assertSame('UserMapper.AddressResult', $association->getResultMapId());
    }

    public function testParseResultMapWithCollection(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <collection property="orders" ofType="Order" columnPrefix="order_">
                        <id property="id" column="id"/>
                        <result property="total" column="total"/>
                    </collection>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertCount(1, $resultMap->getCollections());

        $collection = $resultMap->getCollections()[0];
        $this->assertSame('orders', $collection->getProperty());
        $this->assertSame('Order', $collection->getOfType());
        $this->assertSame('order_', $collection->getColumnPrefix());
    }

    public function testParseResultMapWithCollectionResultMap(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="OrderResult" type="Order">
                    <id property="id" column="id"/>
                </resultMap>
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <collection property="orders" ofType="Order" resultMap="OrderResult"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);

        $collection = $resultMap->getCollections()[0];
        $this->assertSame('UserMapper.OrderResult', $collection->getResultMapId());
    }

    public function testParseResultMapWithDiscriminator(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="AdminResult" type="Admin">
                    <id property="id" column="id"/>
                </resultMap>
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <discriminator column="user_type" phpType="string">
                        <case value="admin" resultMap="AdminResult"/>
                        <case value="regular" resultMap="UserResult"/>
                    </discriminator>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);

        $discriminator = $resultMap->getDiscriminator();
        $this->assertNotNull($discriminator);
        $this->assertSame('user_type', $discriminator->getColumn());
        $this->assertSame('string', $discriminator->getPhpType());
    }

    public function testParseStatementWithIfNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    SELECT * FROM users
                    <if test="name != null">
                        WHERE name = #{name}
                    </if>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithWhereNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    SELECT * FROM users
                    <where>
                        <if test="name != null">
                            AND name = #{name}
                        </if>
                    </where>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithSetNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <update id="updateUser">
                    UPDATE users
                    <set>
                        <if test="name != null">
                            name = #{name},
                        </if>
                    </set>
                    WHERE id = #{id}
                </update>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.updateUser');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithTrimNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    SELECT * FROM users
                    <trim prefix="WHERE" prefixOverrides="AND |OR ">
                        <if test="name != null">
                            AND name = #{name}
                        </if>
                    </trim>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithChooseNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    SELECT * FROM users
                    <choose>
                        <when test="name != null">
                            WHERE name = #{name}
                        </when>
                        <when test="email != null">
                            WHERE email = #{email}
                        </when>
                        <otherwise>
                            WHERE 1=1
                        </otherwise>
                    </choose>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithForEachNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findByIds">
                    SELECT * FROM users WHERE id IN
                    <foreach collection="ids" item="id" open="(" close=")" separator=",">
                        #{id}
                    </foreach>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findByIds');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithForEachNodeWithIndex(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findByIds">
                    SELECT * FROM users WHERE id IN
                    <foreach collection="ids" item="id" index="idx" open="(" close=")" separator=",">
                        #{id}
                    </foreach>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findByIds');
        $this->assertNotNull($statement);
    }

    public function testParseStatementWithBindNode(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    <bind name="pattern" value="'%' + name + '%'"/>
                    SELECT * FROM users WHERE name LIKE #{pattern}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseMultipleStatements(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findById">
                    SELECT * FROM users WHERE id = #{id}
                </select>
                <insert id="insertUser">
                    INSERT INTO users (name) VALUES (#{name})
                </insert>
                <update id="updateUser">
                    UPDATE users SET name = #{name} WHERE id = #{id}
                </update>
                <delete id="deleteUser">
                    DELETE FROM users WHERE id = #{id}
                </delete>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $this->assertNotNull($this->configuration->getMappedStatement('UserMapper.findById'));
        $this->assertNotNull($this->configuration->getMappedStatement('UserMapper.insertUser'));
        $this->assertNotNull($this->configuration->getMappedStatement('UserMapper.updateUser'));
        $this->assertNotNull($this->configuration->getMappedStatement('UserMapper.deleteUser'));
    }

    public function testParseFromFile(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $mapperPath = $this->tempDir . '/UserMapper.xml';
        file_put_contents($mapperPath, $xml);

        $this->parser->parse($mapperPath);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);
    }

    public function testParseFromFileWithMalformedXml(): void
    {
        $mapperPath = $this->tempDir . '/UserMapper.xml';
        file_put_contents($mapperPath, 'not valid xml');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse mapper file:');

        $this->parser->parse($mapperPath);
    }

    public function testParseResultMappingWithPhpType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <result property="age" column="age" phpType="int"/>
                    <result property="email" column="email" phpType="string"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertCount(2, $resultMap->getResultMappings());
    }

    public function testParseResultMappingWithTypeHandler(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <result property="data" column="data" typeHandler="JsonTypeHandler"/>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
    }

    public function testParseComplexMapper(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <result property="name" column="name"/>
                    <association property="address" phpType="Address">
                        <id property="id" column="addr_id"/>
                        <result property="street" column="addr_street"/>
                    </association>
                    <collection property="orders" ofType="Order">
                        <id property="id" column="order_id"/>
                        <result property="total" column="order_total"/>
                    </collection>
                </resultMap>

                <select id="findById" resultMap="UserResult">
                    SELECT
                        u.id, u.name,
                        a.id as addr_id, a.street as addr_street,
                        o.id as order_id, o.total as order_total
                    FROM users u
                    LEFT JOIN addresses a ON u.id = a.user_id
                    LEFT JOIN orders o ON u.id = o.user_id
                    WHERE u.id = #{id}
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertCount(1, $resultMap->getAssociations());
        $this->assertCount(1, $resultMap->getCollections());

        $statement = $this->configuration->getMappedStatement('UserMapper.findById');
        $this->assertNotNull($statement);
        $this->assertSame('UserMapper.UserResult', $statement->getResultMapId());
    }

    public function testParseStatementWithNestedDynamicNodes(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findUsers">
                    SELECT * FROM users
                    <where>
                        <if test="name != null">
                            AND name = #{name}
                        </if>
                        <choose>
                            <when test="status == 'active'">
                                AND status = 'active'
                            </when>
                            <otherwise>
                                AND status != 'deleted'
                            </otherwise>
                        </choose>
                    </where>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findUsers');
        $this->assertNotNull($statement);
    }

    public function testParseAssociationWithoutPhpType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <association property="address">
                        <id property="id" column="addr_id"/>
                    </association>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $association = $resultMap->getAssociations()[0];
        $this->assertSame('object', $association->getPhpType());
    }

    public function testParseCollectionWithoutOfType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <collection property="items">
                        <id property="id" column="item_id"/>
                    </collection>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $collection = $resultMap->getCollections()[0];
        $this->assertSame('object', $collection->getOfType());
    }

    public function testParseDiscriminatorWithoutPhpType(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <resultMap id="BaseResult" type="User">
                    <id property="id" column="id"/>
                </resultMap>
                <resultMap id="UserResult" type="User">
                    <id property="id" column="id"/>
                    <discriminator column="type">
                        <case value="admin" resultMap="BaseResult"/>
                    </discriminator>
                </resultMap>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $resultMap = $this->configuration->getResultMap('UserMapper.UserResult');
        $this->assertNotNull($resultMap);
        $this->assertNotNull($resultMap->getDiscriminator());
    }

    public function testParseForEachWithDefaults(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findByIds">
                    SELECT * FROM users WHERE id IN
                    <foreach collection="ids">
                        #{item}
                    </foreach>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findByIds');
        $this->assertNotNull($statement);
    }

    public function testParseSqlFragment(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <sql id="columns">
                    id, name, email
                </sql>
                <select id="findAll">
                    SELECT <include refid="columns"/> FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);

        // Get bound SQL to verify include was resolved
        $sqlSource = $statement->getSqlSource();
        $this->assertNotNull($sqlSource);

        $boundSql = $sqlSource->getBoundSql([]);
        $this->assertStringContainsString('id, name, email', $boundSql->getSql());
    }

    public function testParseSqlFragmentWithDynamicContent(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <sql id="conditions">
                    <if test="name != null">
                        AND name = #{name}
                    </if>
                </sql>
                <select id="findWithConditions">
                    SELECT * FROM users WHERE 1=1 <include refid="conditions"/>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findWithConditions');
        $this->assertNotNull($statement);
    }

    public function testParseMultipleSqlFragments(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <sql id="columns">id, name, email</sql>
                <sql id="table">users</sql>
                <select id="findAll">
                    SELECT <include refid="columns"/> FROM <include refid="table"/>
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $statement = $this->configuration->getMappedStatement('UserMapper.findAll');
        $this->assertNotNull($statement);

        $sqlSource = $statement->getSqlSource();
        $boundSql = $sqlSource->getBoundSql([]);
        $this->assertStringContainsString('id, name, email', $boundSql->getSql());
        $this->assertStringContainsString('users', $boundSql->getSql());
    }

    public function testIncludeNonExistentFragmentThrows(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll">
                    SELECT <include refid="nonExistent"/> FROM users
                </select>
            </mapper>
            XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SQL fragment not found: nonExistent');

        $this->parser->parseXml($xml);
    }

    public function testParseCacheElementWithDefaults(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <cache/>
                <select id="findAll">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $cacheConfig = $this->configuration->getCacheConfiguration('UserMapper');
        $this->assertNotNull($cacheConfig);
        $this->assertSame('UserMapper', $cacheConfig->namespace);
        $this->assertSame(EvictionPolicy::LRU, $cacheConfig->eviction);
        $this->assertSame(1024, $cacheConfig->size);
        $this->assertTrue($cacheConfig->readOnly);
        $this->assertTrue($cacheConfig->enabled);
    }

    public function testParseCacheElementWithCustomValues(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="OrderMapper">
                <cache
                    type="CustomCacheAdapter"
                    eviction="FIFO"
                    flushInterval="60000"
                    size="512"
                    readOnly="false"/>
                <select id="findAll">
                    SELECT * FROM orders
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $cacheConfig = $this->configuration->getCacheConfiguration('OrderMapper');
        $this->assertNotNull($cacheConfig);
        $this->assertSame('OrderMapper', $cacheConfig->namespace);
        $this->assertSame('CustomCacheAdapter', $cacheConfig->implementation);
        $this->assertSame(EvictionPolicy::FIFO, $cacheConfig->eviction);
        $this->assertSame(60000, $cacheConfig->flushInterval);
        $this->assertSame(512, $cacheConfig->size);
        $this->assertFalse($cacheConfig->readOnly);
    }

    public function testParseCacheElementWithSoftEviction(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="ProductMapper">
                <cache eviction="SOFT"/>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $cacheConfig = $this->configuration->getCacheConfiguration('ProductMapper');
        $this->assertSame(EvictionPolicy::SOFT, $cacheConfig->eviction);
    }

    public function testParseCacheElementWithWeakEviction(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="ProductMapper">
                <cache eviction="WEAK"/>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $cacheConfig = $this->configuration->getCacheConfiguration('ProductMapper');
        $this->assertSame(EvictionPolicy::WEAK, $cacheConfig->eviction);
    }

    public function testMapperWithoutCacheHasNoCacheConfiguration(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <mapper namespace="UserMapper">
                <select id="findAll">
                    SELECT * FROM users
                </select>
            </mapper>
            XML;

        $this->parser->parseXml($xml);

        $cacheConfig = $this->configuration->getCacheConfiguration('UserMapper');
        $this->assertNull($cacheConfig);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
