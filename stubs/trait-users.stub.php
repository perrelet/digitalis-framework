<?php
// Analysed (phpstan.neon.dist `paths`), never loaded: a user for each trait the framework ships for consumers and uses nowhere itself, so
// PHPStan analyses the trait at the configured level instead of reporting it unused.
namespace Lattice\Stubs;

final class Has_Keywords_User extends \Lattice\Post { use \Lattice\Has_Keywords; }
final class Has_Proposals_User extends \Lattice\Post implements \Lattice\Takes_Proposals { use \Lattice\Has_Version, \Lattice\Has_Proposals; }
final class Has_Order_Version_User extends \Lattice\Order { use \Lattice\Has_Order_Version; }
