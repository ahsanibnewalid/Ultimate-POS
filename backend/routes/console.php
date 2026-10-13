<?php
use Illuminate\\Support\\Facades\\Artisan;
Artisan::command('about-pos', function () { $this->comment('Ultimate POS API foundation'); })->purpose('Show application identification');
