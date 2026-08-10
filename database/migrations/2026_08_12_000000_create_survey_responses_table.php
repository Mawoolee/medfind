<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISO/IEC 25010 Evaluation Survey responses.
 *
 * Stores one row per respondent. Each quality characteristic
 * (functional_suitability, usability, security) has multiple
 * question scores (1–5 Likert scale) stored as integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();

            // Who answered (optional — guests can also respond)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('respondent_type')->default('consumer'); // consumer | pharmacy | admin
            $table->string('respondent_name')->nullable();          // optional, for non-logged-in

            // ─── Functional Suitability (3 sub-characteristics) ───────────────
            // FS1 – Functional Completeness: The system provides all functions I need.
            $table->unsignedTinyInteger('fs_completeness')->nullable();
            // FS2 – Functional Correctness: Search results are accurate and reliable.
            $table->unsignedTinyInteger('fs_correctness')->nullable();
            // FS3 – Functional Appropriateness: The system helps me find medicines efficiently.
            $table->unsignedTinyInteger('fs_appropriateness')->nullable();

            // ─── Usability (5 sub-characteristics) ────────────────────────────
            // US1 – Appropriateness Recognisability: The purpose of the system is immediately clear.
            $table->unsignedTinyInteger('us_recognisability')->nullable();
            // US2 – Learnability: I was able to learn how to use the system quickly.
            $table->unsignedTinyInteger('us_learnability')->nullable();
            // US3 – Operability: The system is easy to navigate and use.
            $table->unsignedTinyInteger('us_operability')->nullable();
            // US4 – User Error Protection: The system prevents me from making mistakes.
            $table->unsignedTinyInteger('us_error_protection')->nullable();
            // US5 – User Interface Aesthetics: The interface is visually appealing and well-designed.
            $table->unsignedTinyInteger('us_aesthetics')->nullable();

            // ─── Security (3 sub-characteristics) ─────────────────────────────
            // SE1 – Confidentiality: I trust that my personal/prescription data is kept private.
            $table->unsignedTinyInteger('se_confidentiality')->nullable();
            // SE2 – Integrity: I believe the medicine stock information shown is accurate and trustworthy.
            $table->unsignedTinyInteger('se_integrity')->nullable();
            // SE3 – Accountability: The system clearly shows who updated or verified information.
            $table->unsignedTinyInteger('se_accountability')->nullable();

            // Optional open-ended feedback
            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
