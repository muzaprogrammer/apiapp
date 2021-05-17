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

	function seguridad() {
		extract($_GET);
		$secury = $this->datos->seguridad($user,$pass);
		if (!$secury) {
			$this->response(['msg'=>'No autorizado'],parent::HTTP_UNAUTHORIZED);
		}
		return $secury;
	}

	public function login_get() {
		if ($this->seguridad()) {
			$this->response(['msg'=>"true"],parent::HTTP_OK);
		}
	}

	public function buscar_pacientes_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$pacientes = $this->datos->buscar_pacientes($text);
			if (count($pacientes)>0) {
				$this->response(['pacientes'=>$pacientes],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron pacientes'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function sucursales_get() {
		extract($_GET);
		if ($this->seguridad()) {
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
		if ($this->seguridad()) {
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
		if ($this->seguridad()) {
			$medicos = $this->datos->medicos($idsucursal,$idespecialidad);
			if (count($medicos)>0) {
				$this->response(['medicos'=>$medicos],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron medicos'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function procedimientos_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$procedimientos = $this->datos->procedimientos($idmedico);
			if (count($procedimientos)>0) {
				$this->response(['procedimientos'=>$procedimientos],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron procedimientos'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function horas_desde_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$horas_desde = $this->datos->horas_desde($idprocedimiento,$fecha,$idmedico);
			if (count($horas_desde)>0) {
				$this->response(['horas_desde'=>$horas_desde],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron horas'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function hora_hasta_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$hora_hasta = $this->datos->hora_hasta($hora_inicio,$idprocedimiento,$fecha,$idmedico);
			if (count($hora_hasta)>0) {
				$this->response(['hora_hasta'=>$hora_hasta[0]],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron horas'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function nueva_cita_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$nueva_cita = $this->datos->nueva_cita($user,$pass,$idexpediente,$fecha,$hora_desde,$hora_hasta,$idprocedimiento,$idmedico,$idsucursal,$observaciones);
			if ($nueva_cita>0) {
				$this->response(['nueva_cita'=>$nueva_cita],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se pudo crear la cita'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function estados_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$estados = $this->datos->estados();
			if (count($estados)>0) {
				$this->response(['estados'=>$estados],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron estados'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function ver_citas_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$citas = $this->datos->ver_citas($idestado,$idsucursal,$idespecialidad,$idmedico,$idreservacita);
			if (count($citas)>0) {
				$this->response(['citas'=>$citas],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'No se encontraron citas'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function cambiar_estado_get() {
		extract($_GET);
		if ($this->seguridad()) {
			$cambio = $this->datos->cambiar_estado($idreservacita,$idestado);
			if ($cambio>0) {
				$this->response(['msg'=>'OK'],parent::HTTP_OK);
			} else {
				$this->response(['msg'=>'Hubo un error'],parent::HTTP_NOT_FOUND);
			}
		}
	}

	public function crear_usuario_get() {
		extract($_GET);
		$resp = $this->datos->agregar_usuario($nombre,$usuario,$clave);
		$this->response(['msg'=>$resp],parent::HTTP_OK);
	}
}
