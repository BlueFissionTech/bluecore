<?php
namespace BlueFission\BlueCore\Optimization;

use BlueFission\Behavioral\Configurable;
use BlueFission\Automata\Genetic\Genetic;

class GeneticComponent extends Configurable {

    use Genetic;

    protected $_config = [
        'layout_version' => 1, // integer representing the layout version being used
        'title' => 'Welcome to Our Website!', // page title
        'headline' => 'Discover the Best Products', // main headline
        'subheadline' => 'Trusted by Thousands of Customers', // subheadline
        'body_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit...', // body content
        'button_label' => 'Shop Now', // button label
        'button_color' => '#ff9900', // button color (hex code)
        'background_color' => '#ffffff', // background color (hex code)
        'font_size' => 16, // font size in pixels
        'font_family' => 'Arial, sans-serif', // font family
        'image_src' => 'assets/images/hero.jpg', // image source URL
        'video_src' => 'assets/videos/promo.mp4', // video source URL
        'conversion_goal' => 'purchase', // conversion goal (e.g., 'purchase', 'signup', 'download')
    ];


    protected $_data = [
        'conversions' => 150, // number of conversions
        'bounce_rate' => 0.45, // bounce rate as a decimal (e.g., 0.45 for 45%)
        'scroll_time' => 30, // average time spent scrolling in seconds
        'key_button_clicks' => 200, // number of key button clicks
        'form_submissions' => 50 // number of form submissions
        'visitors' => 1000, // number of visitors
    ];
}
