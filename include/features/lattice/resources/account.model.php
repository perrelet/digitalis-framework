<?php

namespace Digitalis;

// A WordPress account as a resource's record: the framework's User, with a version and unsaved copies for dry runs.
class Account extends User {

    use Has_Version, Has_Unsaved_Copies;

}
