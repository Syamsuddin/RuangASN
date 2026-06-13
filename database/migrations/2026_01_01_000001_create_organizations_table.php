<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('parent_id', 26)->nullable()->index();
            $table->string('type', 30)->default('department');
            $table->string('name', 255);
            $table->string('short_name', 50)->nullable();
            $table->string('code', 30)->nullable()->unique();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date')->default(now());
            $table->date('effective_end_date')->nullable();
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->smallInteger('depth')->default(0);
            $table->char('pemda_id', 26)->nullable()->index();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Self-referencing FK dipisah agar PK 'id' sudah ada saat constraint ditambahkan (wajib di PostgreSQL).
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('organizations')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
