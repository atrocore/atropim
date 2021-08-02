<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Migrations;

/**
 * Migration class for version 1.1.50
 */
class V1Dot1Dot50 extends V1Dot1Dot21
{
    public function up(): void
    {
        $this->execute("INSERT INTO scheduled_job (id, name, job, status, scheduling, created_at, modified_at, is_internal, created_by_id, modified_by_id) VALUES ('updatepfa','UpdatePfa','UpdatePfa','Active','* * * * *','2021-08-02 12:40:02','2021-08-02 12:40:02',1,'system','system')");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM scheduled_job WHERE id='updatepfa'");
        $this->execute("DELETE FROM job WHERE scheduled_job_id='updatepfa'");
    }
}
