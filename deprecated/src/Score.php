<?php

namespace accessibility;

final class DeprecatedScore {
	public static function aggregate(array $scores): array|false {
		if (array_diff(Config::CATEGORIES,array_keys($scores)) || array_diff(array_keys($scores),Config::CATEGORIES)) return false;
		$result = ['categories'=>[],'score'=>100,'total'=>0,'success'=>0,'warning'=>0,'error'=>0];
		foreach ($scores as $category => $values) {
			if (!is_array($values) || array_diff(['total','success','warning','error'],array_keys($values)) || array_diff(array_keys($values),['total','success','warning','error'])) return false;
			foreach ($values as $type => $value) if (!is_int($value) || $value < 0) return false;
			if (abs((float) $values['total'] - (float) ($values['success'] + $values['warning'] + $values['error'])) > 0.000001) return false;
			$result['categories'][$category] = ($values['total'] == 0) ? 1 : ($values['success'] / $values['total']);
			foreach (['total','success','warning','error'] as $type) $result[$type] += (int) $values[$type];
		}
		if ($result['categories']) $result['score'] = scores__calc(array_values($result['categories']),array_fill(0,count($result['categories']),1));
		return $result;
	}
}
