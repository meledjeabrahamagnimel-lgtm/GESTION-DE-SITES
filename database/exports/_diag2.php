<?php
echo 'DB: '.config('database.connections.mysql.database').'@'.config('database.connections.mysql.host').PHP_EOL;
echo 'users (raw): '.\Illuminate\Support\Facades\DB::table('users')->count().PHP_EOL;
\Illuminate\Support\Facades\DB::table('users')->pluck('email')->each(fn ($e) => print($e.PHP_EOL));
