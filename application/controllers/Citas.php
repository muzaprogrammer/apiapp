<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'third_party/REST_Controller.php';
require APPPATH . 'third_party/Format.php';
use Restserver\Libraries\REST_Controller;

class Citas extends REST_Controller {

	function __construct() {
		parent::__construct();
		$this->load->model('Citas_model','datos');
	}

	function seguridad_get() {
		extract($_GET);
		$secury = $this->datos->seguridad($user,$pass);
		if (!$secury) {
			$this->response(['msg'=>'No autorizado'],parent::HTTP_UNAUTHORIZED);
		}
		return $secury;
	}

	public function sucursales_get() {
		extract($_GET);
		if ($this->seguridad_get()) {
			$sucursales = $this->datos->sucursales();
			if (count($sucursales)>0) {
				$this->response(['sucursales'=>$sucursales],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron sucursales'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function especialidades_get() {
		extract($_GET);
		if ($this->seguridad_get()) {
			$especialidades = $this->datos->especialidades($idsucursal);
			if (count($especialidades)>0) {
				$this->response(['especialidades'=>$especialidades],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron especialidades'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function medicos_get() {
		extract($_GET);
		if ($this->seguridad_get()) {
			$medicos = $this->datos->medicos($idsucursal,$idespecialidad);
			if (count($medicos)>0) {
				$this->response(['medicos'=>$medicos],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron medicos'],parent::HTTP_NOT_FOUND);
			}
		}
	}

}
