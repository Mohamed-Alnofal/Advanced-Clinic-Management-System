<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
    {
        // 1. علاقات جدول المرادفات
        Schema::table('synonyms', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        // 2. علاقات جدول التكاليف
        Schema::table('prices', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        // 3. علاقات جدول التفاعلات
        Schema::table('interactions', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        // 4. علاقات جدول الجرعات
        Schema::table('dosages', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        // 5. علاقات جدول الكائنات المستهدفة
        Schema::table('affected_organisms', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        // 6. علاقات جدول الربط للفئات (Pivot Table)
        Schema::table('category_drug', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });

        Schema::table('external_identifiers', function (Blueprint $table) {
            $table->foreign('drug_id')->references('id')->on('drugs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('synonyms', function (Blueprint $table) { $table->dropForeign(['drug_id']); });
        Schema::table('costs', function (Blueprint $table) { $table->dropForeign(['drug_id']); });
        Schema::table('interactions', function (Blueprint $table) { $table->dropForeign(['drug_id']); });
        Schema::table('dosages', function (Blueprint $table) { $table->dropForeign(['drug_id']); });
        Schema::table('organisms', function (Blueprint $table) { $table->dropForeign(['drug_id']); });
        Schema::table('category_drug', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['drug_id']);
        });
        Schema::table('external_identifiers', function (Blueprint $table) { 
            $table->dropForeign(['drug_id']); 
        });
    }
};
