<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->string('name', 255);
            $table->string('code', 30)->nullable();
            $table->string('echelon', 10)->nullable();
            $table->string('position_type', 30);
            $table->smallInteger('grade_level')->nullable();
            $table->boolean('is_head')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
        });

        Schema::create('user_positions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26)->index();
            $table->char('position_id', 26)->index();
            $table->char('organization_id', 26)->index();
            $table->char('direct_superior_id', 26)->nullable()->index();
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('sk_number', 100)->nullable();
            $table->date('sk_date')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('position_id')->references('id')->on('positions');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('direct_superior_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('pemda_id', 26)->index();
            $table->char('organization_id', 26)->nullable()->index();
            $table->string('type', 30);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_cross_opd')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('sk_number', 100)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('organization_id')->references('id')->on('organizations');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('team_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('role', 30)->default('member');
            $table->date('joined_at')->useCurrent();
            $table->date('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['team_id', 'user_id']);
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('delegator_id', 26)->index();
            $table->char('delegate_id', 26)->index();
            $table->char('organization_id', 26)->index();
            $table->string('type', 30);
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->string('sk_number', 100)->nullable();
            $table->char('approved_by', 26)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->foreign('delegator_id')->references('id')->on('users');
            $table->foreign('delegate_id')->references('id')->on('users');
            $table->foreign('organization_id')->references('id')->on('organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('user_positions');
        Schema::dropIfExists('positions');
    }
};
