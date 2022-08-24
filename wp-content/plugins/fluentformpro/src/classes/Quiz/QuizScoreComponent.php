<?php

namespace FluentFormPro\classes\Quiz;


use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use FluentForm\App\Services\FormBuilder\Components\Text;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class QuizScoreComponent extends BaseFieldManager
{
    public function __construct(
        $key = 'quiz_score',
        $title = 'Quiz Score',
        $tags = ['quiz', 'score'],
        $position = 'advanced'
    ) {
        parent::__construct(
            $key,
            $title,
            $tags,
            $position
        );

        add_filter("fluentform_input_data_{$this->key}", [$this, 'addScoreToSubmission'], 10, 4);
    }

    public function getComponent()
    {
        return array(
            'index'          => 6,
            'element'        => $this->key,
            'attributes'     => array(
                'type'  => 'hidden',
                'name'  => 'quiz-score',
                'value' => 'empty',
            ),
            'settings'       => array(
                'admin_field_label' => 'Quiz Score',
                'result_type'       => 'total_point'
            ),
            'editor_options' => array(
                'title'      => __('Quiz Score', 'fluentformpro'),
                'icon_class' => 'el-icon-postcard',
                'template'   => 'inputHidden'
            ),
        );
    }

    public function getGeneralEditorElements()
    {
        return [
            'admin_field_label',
            'name',
            'result_type'
        ];
    }


    public function generalEditorElement()
    {
        return [
            'result_type' => [
                'template'  => 'select',
                'label'     => 'Select Score Type',
                'help_text' => 'Select Score Type that you want to show',
                'options'   => [
                    [
                        'label' => 'Total Point. Example: 70',
                        'value' => 'total_point'
                    ],
                    [
                        'label' => 'Total Correct Questions. Example: 6',
                        'value' => 'total_correct'
                    ],
                    [
                        'label' => 'Fraction Point. Example: 6/10',
                        'value' => 'fraction_point'
                    ],
                    [
                        'label' => 'Grade System. Example: A',
                        'value' => 'grade'
                    ],
                    [
                        'label' => 'Percentage. Example: 70%',
                        'value' => 'percent'
                    ]
                ]
            ]
        ];
    }

    public function render($data, $form)
    {
        return (new Text())->compile($data, $form);
    }

    public function addScoreToSubmission($value, $field, $submissionData, $form)
    {
        $quizController = new \FluentFormPro\classes\Quiz\QuizController();
        $quizSettings = $quizController->getSettings($form->id);
        
        $quizResults = $quizController->getFormattedResults($quizSettings, $submissionData, $form);
        $score = 0;
        $totalCorrect = 0;
        $totalPoints = 0;
        $advancePoints = 0;
        foreach ($quizResults as $result) {
            $totalPoints += $result['points'];
            if ($result['correct'] == true) {
                $score += $result['points'];
                $totalCorrect++;
            }
            $advancePoints += $result['advance_points'];
        }

        $scoreType = Arr::get($field, 'raw.settings.result_type');
        $result = apply_filters('fluentform_quiz_no_grade_label', __('Not Graded', 'fluentformpro'));
        switch ($scoreType) {
            case 'total_point':
                $result = $advancePoints;
                break;
            case 'total_correct_point':
                $result = $score;
                break;
            case 'total_correct':
                $result = $totalCorrect;
                break;
            case 'fraction_point':
                $result = $totalCorrect . '/' . count($quizResults);
                break;
            case 'percent':
                $result = number_format((($score / $totalPoints) * 100), 2) . '%';
                break;
            case 'grade':
                $grades = $quizSettings['grades'];
                foreach ($grades as $grade) {
                    if (($score >= Arr::get($grade, 'min')) && ($score <= Arr::get($grade, 'max'))) {
                        $result = Arr::get($grade, 'label');
                    }
                }
                break;
        }
        return apply_filters('fluentform_quiz_score_value', $result, $form->id, $scoreType, $quizResults);
    }

}
