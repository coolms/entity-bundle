<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Tests\Factory;

use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Core\Service\DataFormat;
use CoolMS\EntityBundle\Factory\EntityFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A combined interface for mocking: the concrete Serializer class has final methods
 * (decode, serialize, encode) that cannot be configured, so we mock against this interface.
 */
interface FullSerializerInterface extends SerializerInterface, NormalizerInterface, DecoderInterface
{
}

#[AllowMockObjectsWithoutExpectations]
class EntityFactoryTest extends TestCase
{
    private FullSerializerInterface&MockObject $serializer;
    private DenormalizerInterface&MockObject $denormalizer;
    private ParameterBagInterface&MockObject $parameterBag;
    private EntityFactory $factory;
    private object $entityStub;

    // process() — array input

    /**
     * @throws ExceptionInterface
     */
    public function testProcessWithArrayDataCallsDenormalizerWithNullFormat(): void
    {
        $data = ['name' => 'test'];
        $this->denormalizer->expects($this->once())->method('denormalize')
            ->with($data, $this->entityStub::class, null, [])->willReturn($this->entityStub);
        $result = $this->factory->process($data);
        $this->assertSame($this->entityStub, $result);
    }

    public function testProcessWithArrayDataDoesNotCallDecode(): void
    {
        $this->serializer->expects($this->never())->method('decode');
        $this->denormalizer->method('denormalize')->willReturn($this->entityStub);
        $this->factory->process(['name' => 'test']);
    }

    // process() — string input

    public function testProcessWithJsonStringDecodesBeforeDenormalizing(): void
    {
        $this->serializer->expects($this->once())->method('decode')
            ->with('{}', 'json', [])->willReturn([]);
        $this->denormalizer->method('denormalize')->willReturn($this->entityStub);
        $this->factory->process('{}', DataFormat::JSON);
    }

    public function testProcessWithXmlStringDecodesBeforeDenormalizing(): void
    {
        $this->serializer->expects($this->once())->method('decode')
            ->with('<r/>', 'xml', [])->willReturn([]);
        $this->denormalizer->method('denormalize')->willReturn($this->entityStub);
        $this->factory->process('<r/>', DataFormat::XML);
    }

    public function testProcessWithYamlStringDecodesBeforeDenormalizing(): void
    {
        $this->serializer->expects($this->once())->method('decode')
            ->with('name: test', 'yaml', [])->willReturn(['name' => 'test']);
        $this->denormalizer->method('denormalize')->willReturn($this->entityStub);
        $this->factory->process('name: test', DataFormat::YAML);
    }

    public function testProcessThrowsWhenStringPassedWithoutFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->process('{}');
    }

    // transform()

    public function testTransformToJsonCallsSerialize(): void
    {
        $this->serializer->expects($this->once())->method('serialize')
            ->with($this->entityStub, 'json', [])->willReturn('{}');
        $this->assertIsString($this->factory->provide($this->entityStub, DataFormat::JSON));
    }

    public function testTransformToXmlCallsSerialize(): void
    {
        $this->serializer->expects($this->once())->method('serialize')
            ->with($this->entityStub, 'xml', [])->willReturn('<r/>');
        $this->assertIsString($this->factory->provide($this->entityStub, DataFormat::XML));
    }

    public function testTransformToYamlCallsSerialize(): void
    {
        $this->serializer->expects($this->once())->method('serialize')
            ->with($this->entityStub, 'yaml', [])->willReturn('id: x');
        $this->assertIsString($this->factory->provide($this->entityStub, DataFormat::YAML));
    }

    public function testTransformToArrayCallsNormalize(): void
    {
        $this->serializer->expects($this->once())->method('normalize')
            ->with($this->entityStub, 'array', [])->willReturn(['id' => 'x']);
        $this->serializer->expects($this->never())->method('serialize');
        $this->assertIsArray($this->factory->provide($this->entityStub, DataFormat::ARRAY));
    }

    // update()

    public function testUpdateThrowsWhenObjectToPopulateMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->update(['name' => 'x']);
    }

    public function testUpdateThrowsWhenObjectToPopulateIsWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->update(['name' => 'x'], null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => new stdClass(),
        ]);
    }

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(FullSerializerInterface::class);
        $this->denormalizer = $this->createMock(DenormalizerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->entityStub = new class implements IdentifierProviderInterface {
            public Uuid $id {
                get => Uuid::v7();
            }
            public ?string $idAsString {
                get => $this->id->toString();
            }
        };

        $this->factory = new EntityFactory(
            objectClass: $this->entityStub::class,
            serializer: $this->serializer,
            denormalizer: $this->denormalizer,
            parameterBag: $this->parameterBag,
        );
    }
}
