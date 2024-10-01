<?php declare(strict_types = 1);

namespace QaData\DiAttribute\DI;

use Nette;
use QaData\DiAttribute\Attribute\DiRegisterService;
use ReflectionClass;
use stdClass;
use function array_filter;
use function class_exists;
use function count;
use function sprintf;
use function str_starts_with;

/** @method stdClass getConfig() */
final class DiAttributeExtension extends Nette\DI\CompilerExtension
{

	/** @var array<int, string> */
	private array $discoveredServices = [];

	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::structure([
			'paths' => Nette\Schema\Expect::arrayOf('string'),
			'excludes' => Nette\Schema\Expect::arrayOf('string'),
		]);
	}

	public function loadConfiguration(): void
	{
		$config = $this->getConfig();

		$this->discoveredServices = $this->findClassesForRegistration(
			$config->paths,
			$config->excludes,
		);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$counter = 1;
		foreach ($this->discoveredServices as $class) {

			if ($builder->getByType($class) !== null) {
				continue;
			}

			$name = sprintf('di.service.%s', $counter++);
			$builder->addDefinition($this->prefix($name))
				->setFactory($class)
				->setType($class);
		}
	}

	/**
	 * @param array<string> $dirs
	 * @param array<string> $excludes
	 * @return array<string>
	 */
	protected function findClassesForRegistration(array $dirs, array $excludes = []): array
	{
		$loader = $this->createRobotLoader();
		$loader->addDirectory(...$dirs);
		$loader->rebuild();

		$indexed = $loader->getIndexedClasses();

		$classes = [];
		foreach ($indexed as $class => $file) {

			$excludedClasses = static fn (string $exclude): bool => str_starts_with($class, $exclude);
			if (count(array_filter($excludes, $excludedClasses)) > 0) {
				continue;
			}

			if (!class_exists($class)) {
				continue;
			}

			$ct = new ReflectionClass($class);

			if ($ct->isAbstract()) {
				continue;
			}

			foreach ($ct->getAttributes() as $attribute) {

				$attributeInstance = $attribute->newInstance();

				if ($attributeInstance instanceof DiRegisterService === false) {
					continue;
				}

				$classes[] = $class;
			}
		}

		return $classes;
	}

	protected function createRobotLoader(): Nette\Loaders\RobotLoader
	{
		if (!class_exists(Nette\Loaders\RobotLoader::class)) {
			throw new Nette\InvalidStateException('Install nette/robot-loader at first');
		}

		return new Nette\Loaders\RobotLoader();
	}

}
