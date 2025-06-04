<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyListView extends BaseView
{
    protected function content(): string
    {
        $surveys = $this->get('surveys', []);

        $surveyItems = '';
        if (empty($surveys)) {
            $surveyItems = '<p>Наразі немає активних опитувань.</p>';
        } else {
            foreach ($surveys as $survey) {
                $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);
                $surveyItems .= "
                    <div class='survey-item'>
                        <h3>" . $this->escape($survey['title']) . "</h3>
                        <p>" . $this->escape($survey['description']) . "</p>
                        <p><small>Автор: " . $this->escape($survey['author_name']) . " | Відповідей: {$responseCount}</small></p>
                        <div class='survey-actions'>
                            <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Пройти опитування</a>
                            <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                        </div>
                    </div>";
            }
        }

        $createButton = '';
        if (Session::isLoggedIn()) {
            $createButton = "<a href='/surveys/create' class='btn btn-success'>Створити нове опитування</a>";
        }

        return "
            <div class='container'>
                <div class='header-actions'>
                    <h1>Доступні опитування</h1>
                    " . $this->component('Navigation') . "
                </div>
                
                <div class='survey-list'>
                    {$surveyItems}
                </div>
                
                <div class='page-actions'>
                    {$createButton}
                    <a href='/' class='btn btn-secondary'>На головну</a>
                    " . (Session::isLoggedIn() ? "<a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>" : "") . "
                </div>
            </div>";
    }
}