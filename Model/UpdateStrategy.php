<?php

namespace Irs\CmsSetup\Model;

/**
 * Block & pages update rules
 */
enum UpdateStrategy: string
{
    /**
     * If block or page already exists and has allow_overwrite
     * set to 0 an error will be thrown. Otherwise, it will be updated.
     */
    case Error = 'error';

    /**
     * If block or page already exists and has allow_overwrite
     * set to 0 it won't be updated. Otherwise, it will be updated.
     */
    case Skip  = 'skip';

    /**
     * If block or page already exists it will be updated in any case.
     */
    case Force = 'force';
}
